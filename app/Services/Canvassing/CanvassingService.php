<?php

declare(strict_types=1);

namespace App\Services\Canvassing;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\QualityFlag;
use App\Enums\RequestChannel;
use App\Enums\ScopeType;
use App\Enums\WalkListEntryStatus;
use App\Enums\WalkListStatus;
use App\Models\ContactAttempt;
use App\Models\Supporter;
use App\Models\User;
use App\Models\WalkList;
use App\Models\WalkListEntry;
use App\Models\Ward;
use App\Notifications\WalkListAssigned;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\ConsentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Walk lists and field contact capture. Contact capture is idempotent on a
 * client-generated UUID so retried (offline) submissions never duplicate.
 */
final class CanvassingService
{
    /** Contacts with the same supporter within this window are flagged as possible duplicates. */
    private const int DUPLICATE_WINDOW_MINUTES = 60;

    public function __construct(private readonly AuditLogger $audit, private readonly ConsentService $consent) {}

    /**
     * @param  list<string>  $labels  Street or landmark stops (no personal data).
     */
    public function createWalkList(User $by, string $name, Ward $ward, array $labels = [], bool $includeSupporters = false, ?\DateTimeInterface $dueOn = null, ?string $notes = null): WalkList
    {
        if (! $by->hasPermission('canvassing.manage') || ! $by->geoScope('canvassing.manage')->covers(ScopeType::Ward, $ward->id)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $labels = array_values(array_filter(array_map(fn ($l) => mb_substr(trim((string) $l), 0, 200), $labels)));

        return DB::transaction(function () use ($by, $name, $ward, $labels, $includeSupporters, $dueOn, $notes): WalkList {
            $list = WalkList::query()->create([
                'name' => mb_substr($name, 0, 150),
                'ward_id' => $ward->id,
                'lga_id' => $ward->lga_id,
                'created_by' => $by->id,
                'status' => WalkListStatus::Draft,
                'due_on' => $dueOn,
                'notes' => $notes,
            ]);

            $position = 0;
            foreach ($labels as $label) {
                $list->entries()->create(['label' => $label, 'position' => ++$position]);
            }

            if ($includeSupporters) {
                // Supporters in the ward who are not erased, not restricted and not yet followed up recently.
                Supporter::query()->where('ward_id', $ward->id)->whereNull('erased_at')->where('processing_restricted', false)
                    ->whereDoesntHave('contactAttempts', fn ($q) => $q->where('occurred_at', '>', now()->subDays(14)))
                    ->orderBy('full_name')->limit(200)->get(['id', 'full_name', 'reference'])
                    ->each(function (Supporter $s) use ($list, &$position): void {
                        $list->entries()->create(['supporter_id' => $s->id, 'label' => $s->full_name.' ('.$s->reference.')', 'position' => ++$position]);
                    });
            }

            if ($position === 0) {
                throw ValidationException::withMessages(['labels' => __('canvassing.empty_list')]);
            }

            $this->audit->record('walk_list.created', $list, null, ['entries' => $position], $by);

            return $list;
        });
    }

    public function assign(WalkList $list, User $assignee, User $by): void
    {
        $this->assertCanManage($list, $by);

        if (! $assignee->isActive() || ! $assignee->hasPermission('canvassing.record') || ! $list->isWithin($assignee->geoScope('canvassing.record'))) {
            throw ValidationException::withMessages(['assignee' => __('canvassing.assignee_not_in_area')]);
        }

        $list->forceFill(['assigned_to' => $assignee->id, 'status' => $list->status === WalkListStatus::Draft ? WalkListStatus::Assigned : $list->status, 'version' => $list->version + 1])->save();
        $assignee->notify(new WalkListAssigned($list));
        $this->audit->record('walk_list.assigned', $list, null, ['assignee' => $assignee->id], $by);
    }

    /**
     * Records a contact at a walk-list stop. Returns the existing record when the UUID was already used.
     */
    public function recordContact(
        WalkListEntry $entry,
        User $by,
        ContactOutcome $outcome,
        ContactChannel $channel = ContactChannel::Door,
        ?string $notes = null,
        ?string $uuid = null,
        ?\DateTimeInterface $occurredAt = null,
        ?string $deviceId = null,
        bool $offline = false,
    ): ContactAttempt {
        $uuid ??= (string) Str::uuid();

        if ($existing = ContactAttempt::query()->where('uuid', $uuid)->first()) {
            return $existing;
        }

        $entry->loadMissing('walkList', 'supporter');
        $list = $entry->walkList;
        $this->assertCanRecord($list, $by);

        if ($list->status === WalkListStatus::Archived) {
            throw ValidationException::withMessages(['list' => __('canvassing.archived')]);
        }

        return DB::transaction(function () use ($entry, $list, $by, $outcome, $channel, $notes, $uuid, $occurredAt, $deviceId, $offline): ContactAttempt {
            $supporter = $entry->supporter;
            $occurred = $occurredAt ? Carbon::instance($occurredAt) : now();

            // Offline clocks can be wrong; never accept future times.
            if ($occurred->isFuture()) {
                $occurred = now();
            }

            $duplicate = $supporter !== null && ContactAttempt::query()->where('supporter_id', $supporter->id)
                ->whereBetween('occurred_at', [$occurred->copy()->subMinutes(self::DUPLICATE_WINDOW_MINUTES), $occurred->copy()->addMinutes(self::DUPLICATE_WINDOW_MINUTES)])
                ->exists();

            $attempt = ContactAttempt::query()->create([
                'uuid' => $uuid,
                'supporter_id' => $supporter?->id,
                'walk_list_entry_id' => $entry->id,
                'user_id' => $by->id,
                'senatorial_district_id' => $list->senatorial_district_id,
                'lga_id' => $list->lga_id,
                'ward_id' => $list->ward_id,
                'polling_unit_id' => $list->polling_unit_id,
                'channel' => $channel,
                'outcome' => $outcome,
                'notes_encrypted' => $notes === null || trim($notes) === '' ? null : mb_substr($notes, 0, 2000),
                'occurred_at' => $occurred,
                'quality_flag' => $duplicate ? QualityFlag::SuspectDuplicate : QualityFlag::Ok,
                'device_id' => $deviceId,
                'recorded_offline' => $offline,
            ]);

            $entry->forceFill(['status' => WalkListEntryStatus::Visited])->save();

            if ($outcome === ContactOutcome::DoNotContact && $supporter?->phone_hash) {
                $this->consent->withdrawAll($supporter->phone_hash, null, RequestChannel::Field, $supporter, ['contact_attempt' => $uuid], $by);
            }

            $progress = $list->progress();
            $status = $progress['done'] >= $progress['total'] ? WalkListStatus::Completed : WalkListStatus::InProgress;
            if ($list->status !== $status) {
                $list->forceFill(['status' => $status])->save();
            }

            return $attempt;
        });
    }

    public function skip(WalkListEntry $entry, User $by): void
    {
        $this->assertCanRecord($entry->loadMissing('walkList')->walkList, $by);
        $entry->forceFill(['status' => WalkListEntryStatus::Skipped])->save();
    }

    public function review(ContactAttempt $attempt, QualityFlag $flag, User $by): void
    {
        if (! $by->hasPermission('canvassing.manage') || ! $attempt->isWithin($by->geoScope('canvassing.manage'))) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $attempt->forceFill(['quality_flag' => $flag, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        $this->audit->record('contact.reviewed', $attempt, null, ['flag' => $flag->value], $by);
    }

    public function archive(WalkList $list, User $by): void
    {
        $this->assertCanManage($list, $by);
        $list->forceFill(['status' => WalkListStatus::Archived])->save();
        $this->audit->record('walk_list.archived', $list, actor: $by);
    }

    public function canRecord(WalkList $list, User $user): bool
    {
        return ($list->assigned_to === $user->id && $user->hasPermission('canvassing.record'))
            || ($user->hasPermission('canvassing.manage') && $list->isWithin($user->geoScope('canvassing.manage')));
    }

    private function assertCanRecord(WalkList $list, User $by): void
    {
        if (! $this->canRecord($list, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }

    private function assertCanManage(WalkList $list, User $by): void
    {
        if (! $by->hasPermission('canvassing.manage') || ! $list->isWithin($by->geoScope('canvassing.manage'))) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
