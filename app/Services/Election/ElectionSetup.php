<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\ElectionStatus;
use App\Models\Election;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates and configures elections. Party lists must be copied from the
 * official ballot; the platform ships with none.
 */
final class ElectionSetup
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, election_date: string, checklist: list<string>, turnout_times?: list<string>}  $data
     * @param  list<array{code: string, name: string}>  $parties
     */
    public function save(User $by, array $data, array $parties, ?Election $election = null): Election
    {
        $this->require($by);

        $checklist = collect($data['checklist'])->map(fn ($l) => trim((string) $l))->filter()->unique()->values()
            ->map(fn (string $label) => ['key' => Str::slug($label, '_') ?: 'item', 'label' => mb_substr($label, 0, 150)])->all();
        $parties = collect($parties)->map(fn ($p) => ['code' => strtoupper(trim((string) $p['code'])), 'name' => trim((string) $p['name'])])
            ->filter(fn ($p) => $p['code'] !== '')->values();

        if ($parties->pluck('code')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['parties' => __('election.duplicate_party')]);
        }
        if ($parties->contains(fn ($p) => preg_match('/^[A-Z0-9]{1,12}$/', $p['code']) !== 1 || $p['name'] === '')) {
            throw ValidationException::withMessages(['parties' => __('election.party_format')]);
        }
        if ($election !== null && $election->status !== ElectionStatus::Setup && $parties->pluck('code')->sort()->values()->all() !== $election->parties->pluck('code')->sort()->values()->all()) {
            throw ValidationException::withMessages(['parties' => __('election.parties_locked')]);
        }

        return DB::transaction(function () use ($by, $data, $checklist, $parties, $election): Election {
            $election ??= new Election(['created_by' => $by->id]);
            $election->fill([
                'name' => mb_substr(trim($data['name']), 0, 150),
                'election_date' => $data['election_date'],
                'checklist' => $checklist,
                'turnout_times' => array_values(array_filter($data['turnout_times'] ?? [], fn ($t) => preg_match('/^\d{2}:\d{2}$/', (string) $t) === 1)),
            ])->save();

            $election->parties()->whereNotIn('code', $parties->pluck('code'))->delete();
            foreach ($parties as $i => $party) {
                $election->parties()->updateOrCreate(['code' => $party['code']], ['name' => mb_substr($party['name'], 0, 150), 'position' => $i]);
            }

            $this->audit->record($election->wasRecentlyCreated ? 'election.created' : 'election.updated', $election, null, ['parties' => $parties->count(), 'checklist' => count($checklist)], $by);

            return $election->load('parties');
        });
    }

    public function activate(Election $election, User $by): void
    {
        $this->require($by);
        if ($election->parties()->count() < 2) {
            throw ValidationException::withMessages(['parties' => __('election.need_parties')]);
        }

        DB::transaction(function () use ($election, $by): void {
            Election::query()->where('status', ElectionStatus::Active)->whereKeyNot($election->id)->update(['status' => ElectionStatus::Closed]);
            $election->forceFill(['status' => ElectionStatus::Active])->save();
            $this->audit->record('election.activated', $election, null, [], $by);
        });
    }

    public function close(Election $election, User $by): void
    {
        $this->require($by);
        $election->forceFill(['status' => ElectionStatus::Closed])->save();
        $this->audit->record('election.closed', $election, null, [], $by);
    }

    private function require(User $user): void
    {
        if (! $user->hasPermission('election.setup')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
