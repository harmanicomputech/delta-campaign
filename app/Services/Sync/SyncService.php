<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ContactOutcome;
use App\Enums\WalkListStatus;
use App\Models\ContactAttempt;
use App\Models\User;
use App\Models\WalkList;
use App\Models\WalkListEntry;
use App\Services\Canvassing\CanvassingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Applies a batch of records captured offline. Each item succeeds or fails on
 * its own (partial success), and every item is idempotent on its client UUID,
 * so a device can safely resend a whole batch after a dropped connection.
 *
 * Item results:
 *  - created:   stored now
 *  - duplicate: already stored earlier (same UUID); the device can forget it
 *  - conflict:  valid, but the server state no longer allows it (list archived,
 *               reassigned to someone else); the device shows it for review
 *  - rejected:  invalid item; retrying will not help
 *  - error:     unexpected server failure; the device retries later
 */
final class SyncService
{
    public const int MAX_ITEMS = 100;

    /** Offline captures older than this are refused (clock errors, stale devices). */
    public const int MAX_AGE_DAYS = 14;

    public function __construct(private readonly CanvassingService $canvassing) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{uuid: string|null, status: string, message?: string, id?: int}>
     */
    public function process(User $user, ?string $deviceId, array $items): array
    {
        return array_map(fn (array $item): array => $this->one($user, $deviceId, $item), $items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{uuid: string|null, status: string, message?: string, id?: int}
     */
    private function one(User $user, ?string $deviceId, array $item): array
    {
        $uuid = is_string($item['uuid'] ?? null) ? $item['uuid'] : null;

        $validator = Validator::make($item, [
            'type' => ['required', 'in:contact'],
            'uuid' => ['required', 'uuid'],
            'walk_list_entry_id' => ['required', 'integer'],
            'outcome' => ['required', 'in:'.implode(',', array_column(ContactOutcome::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => $validator->errors()->first()];
        }

        if ($existing = ContactAttempt::query()->where('uuid', $uuid)->first()) {
            // The same UUID from a different person is never merged into their record.
            return $existing->user_id === $user->id
                ? ['uuid' => $uuid, 'status' => 'duplicate', 'id' => $existing->id]
                : ['uuid' => $uuid, 'status' => 'rejected', 'message' => __('sync.uuid_taken')];
        }

        $occurred = Carbon::parse((string) $item['occurred_at']);
        if ($occurred->lt(now()->subDays(self::MAX_AGE_DAYS))) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => __('sync.too_old', ['days' => self::MAX_AGE_DAYS])];
        }

        $entry = WalkListEntry::query()->with('walkList')->find((int) $item['walk_list_entry_id']);
        if ($entry === null) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'message' => __('sync.not_found')];
        }

        if ($entry->walkList->status === WalkListStatus::Archived) {
            return ['uuid' => $uuid, 'status' => 'conflict', 'message' => __('sync.archived', ['name' => $entry->walkList->name])];
        }

        if (! $this->canvassing->canRecord($entry->walkList, $user)) {
            return ['uuid' => $uuid, 'status' => 'conflict', 'message' => __('sync.not_assigned', ['name' => $entry->walkList->name])];
        }

        try {
            $attempt = $this->canvassing->recordContact(
                $entry, $user, ContactOutcome::from((string) $item['outcome']),
                notes: isset($item['notes']) ? (string) $item['notes'] : null,
                uuid: $uuid, occurredAt: $occurred, deviceId: $deviceId, offline: true,
            );
        } catch (Throwable $e) {
            report($e);

            return ['uuid' => $uuid, 'status' => 'error', 'message' => __('sync.server_error')];
        }

        return ['uuid' => $uuid, 'status' => 'created', 'id' => $attempt->id];
    }

    /**
     * Walk lists the person is working on, in the minimal form the device caches (encrypted) for offline use.
     *
     * @return list<array<string, mixed>>
     */
    public function walkListsFor(User $user): array
    {
        if (! $user->hasPermission('canvassing.record')) {
            return [];
        }

        return WalkList::query()->where('assigned_to', $user->id)->whereIn('status', [WalkListStatus::Assigned, WalkListStatus::InProgress])
            ->with(['ward:id,name', 'entries' => fn ($q) => $q->orderBy('position')->with('supporter:id,full_name,reference')])
            ->latest('id')->limit(10)->get()
            ->map(fn (WalkList $list): array => [
                'uuid' => $list->uuid,
                'name' => $list->name,
                'ward' => $list->ward?->name,
                'due_on' => $list->due_on?->toDateString(),
                'entries' => $list->entries->map(fn (WalkListEntry $e): array => [
                    'id' => $e->id,
                    'position' => $e->position,
                    'label' => $e->supporter?->full_name ?? $e->label,
                    'reference' => $e->supporter?->reference,
                    'status' => $e->status->value,
                ])->all(),
            ])->all();
    }
}
