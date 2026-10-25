<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\ElectionStatus;
use App\Enums\ScopeType;
use App\Jobs\SendTransactionalSms;
use App\Models\AgentAssignment;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns polling agents. Assignment grants the polling-agent role for that
 * one polling unit; withdrawal revokes it, so access ends immediately.
 */
final class AgentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(User $by, Election $election, PollingUnit $unit, string $name, string $phone, string $role = 'primary'): AgentAssignment
    {
        if (! $this->canManageUnit($by, $unit)) {
            throw new AuthorizationDenied(__('election.unit_outside_area'));
        }
        if ($election->status === ElectionStatus::Closed) {
            throw ValidationException::withMessages(['election' => __('election.closed')]);
        }
        if (! in_array($role, ['primary', 'backup'], true)) {
            throw ValidationException::withMessages(['role' => __('validation.in', ['attribute' => 'role'])]);
        }
        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);

        $assignment = DB::transaction(function () use ($by, $election, $unit, $name, $e164, $role): AgentAssignment {
            $user = User::findByPhone($e164);
            if ($user === null) {
                $user = new User(['name' => mb_substr(trim($name), 0, 150)]);
                $user->setPhone($e164);
                $user->save();
            } elseif (! $user->isActive()) {
                throw ValidationException::withMessages(['phone' => __('election.account_inactive')]);
            }

            if (AgentAssignment::query()->where('election_id', $election->id)->where('user_id', $user->id)->where('status', 'assigned')->exists()) {
                throw ValidationException::withMessages(['phone' => __('election.already_assigned')]);
            }
            if ($role === 'primary' && AgentAssignment::query()->where('election_id', $election->id)->where('polling_unit_id', $unit->id)->where('status', 'assigned')->where('role', 'primary')->exists()) {
                throw ValidationException::withMessages(['role' => __('election.primary_taken')]);
            }

            $roleId = Role::query()->where('slug', 'polling_agent')->value('id');
            $grant = RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roleId, 'scope_type' => ScopeType::PollingUnit, 'scope_id' => $unit->id, 'assigned_by' => $by->id]);
            $user->flushAuthorizationCache();

            $assignment = AgentAssignment::query()->create([
                'election_id' => $election->id, 'user_id' => $user->id, 'polling_unit_id' => $unit->id, 'ward_id' => $unit->ward_id,
                'lga_id' => $unit->lga_id, 'senatorial_district_id' => $unit->senatorial_district_id, 'role' => $role,
                'role_assignment_id' => $grant->id, 'assigned_by' => $by->id,
            ]);
            $this->audit->record('agent.assigned', $assignment, null, ['polling_unit' => $unit->code, 'role' => $role, 'user_id' => $user->id], $by);

            return $assignment;
        });

        SendTransactionalSms::dispatch($e164, __('election.sms_assigned', ['unit' => $unit->code, 'app' => config('app.name'), 'url' => route('login')]), 'agent.assigned');

        return $assignment;
    }

    public function withdraw(User $by, AgentAssignment $assignment, string $reason): void
    {
        $assignment->loadMissing('pollingUnit');
        if (! $this->canManageUnit($by, $assignment->pollingUnit)) {
            throw new AuthorizationDenied(__('election.unit_outside_area'));
        }
        if (! $assignment->isActive()) {
            return;
        }

        DB::transaction(function () use ($by, $assignment, $reason): void {
            $assignment->forceFill(['status' => 'withdrawn', 'withdrawn_at' => now()])->save();
            RoleAssignment::query()->whereKey($assignment->role_assignment_id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $by->id]);
            $assignment->user->flushAuthorizationCache();
            $this->audit->record('agent.withdrawn', $assignment, null, ['reason' => mb_substr($reason, 0, 300)], $by);
        });
    }

    public function canManageUnit(User $user, PollingUnit $unit): bool
    {
        return $user->hasPermission('polling-agents.manage')
            && $user->geoScope('polling-agents.manage')->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id);
    }

    /** The agent's active assignment in the active election. */
    public function activeAssignmentFor(User $user): ?AgentAssignment
    {
        $election = Election::active();

        return $election === null ? null : AgentAssignment::query()->with(['pollingUnit', 'election.parties'])
            ->where('election_id', $election->id)->where('user_id', $user->id)->where('status', 'assigned')->first();
    }
}
