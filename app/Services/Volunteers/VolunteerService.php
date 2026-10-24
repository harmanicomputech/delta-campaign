<?php

declare(strict_types=1);

namespace App\Services\Volunteers;

use App\Enums\Availability;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Enums\VolunteerSkill;
use App\Enums\VolunteerStatus;
use App\Jobs\SendTransactionalSms;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Supporter;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Authentication\SignInService;
use App\Services\Users\AuthorizationDenied;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Volunteer lifecycle: applied → approved (account with the volunteer role at
 * their ward) / rejected; approved ⇄ suspended; reassignment between wards.
 */
final class VolunteerService
{
    public function __construct(private readonly AuditLogger $audit, private readonly SignInService $sessions) {}

    /**
     * @param  array{full_name: string, phone: string, lga_id?: int|null, ward_id?: int|null, skills?: list<string>, availability?: list<string>}  $data
     */
    public function apply(array $data, string $source, ?User $by = null, bool $sample = false): Volunteer
    {
        $e164 = NigerianPhoneNumber::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
        $hash = PhoneHasher::hash($e164);

        if ($existing = Volunteer::query()->where('phone_hash', $hash)->first()) {
            return $existing;
        }

        $ward = empty($data['ward_id']) ? null : Ward::query()->findOrFail($data['ward_id']);

        if ($ward === null && empty($data['lga_id'])) {
            throw ValidationException::withMessages(['lga_id' => __('supporters.lga_required')]);
        }

        return DB::transaction(function () use ($data, $source, $by, $sample, $e164, $hash, $ward): Volunteer {
            $volunteer = new Volunteer([
                'full_name' => mb_substr(trim((string) preg_replace('/\s+/', ' ', $data['full_name'])), 0, 150),
                'lga_id' => $ward?->lga_id ?? (int) $data['lga_id'],
                'ward_id' => $ward?->id,
                'status' => VolunteerStatus::Applied,
                'skills' => array_values(array_intersect($data['skills'] ?? [], array_column(VolunteerSkill::cases(), 'value'))),
                'availability' => array_values(array_intersect($data['availability'] ?? [], array_column(Availability::cases(), 'value'))),
                'source' => $source,
                'applied_at' => now(),
                'is_sample' => $sample,
                'supporter_id' => Supporter::query()->where('phone_hash', $hash)->value('id'),
            ]);
            $volunteer->setPhone($e164)->save();

            WorkflowTransition::record($volunteer, null, VolunteerStatus::Applied->value, $by, 'Applied via '.$source);
            $this->audit->record('volunteer.applied', $volunteer, null, ['source' => $source], $by);

            return $volunteer;
        });
    }

    public function approve(Volunteer $volunteer, User $approver): Volunteer
    {
        $this->assertCanDecide($volunteer, $approver);

        if ($volunteer->status !== VolunteerStatus::Applied && $volunteer->status !== VolunteerStatus::Rejected) {
            throw ValidationException::withMessages(['status' => __('volunteers.not_pending')]);
        }

        if ($volunteer->ward_id === null) {
            throw ValidationException::withMessages(['ward_id' => __('volunteers.ward_required')]);
        }

        $user = DB::transaction(function () use ($volunteer, $approver): User {
            $user = User::query()->where('phone_hash', $volunteer->phone_hash)->first();

            if ($user === null) {
                $user = new User(['name' => $volunteer->full_name]);
                $user->setPhone((string) $volunteer->phone_encrypted)->save();
            }

            $this->grantVolunteerRole($user, (int) $volunteer->ward_id, $approver);

            $from = $volunteer->status;
            $volunteer->forceFill([
                'status' => VolunteerStatus::Approved,
                'user_id' => $user->id,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_reason' => null,
                'version' => $volunteer->version + 1,
            ])->save();

            WorkflowTransition::record($volunteer, $from->value, VolunteerStatus::Approved->value, $approver);
            $this->audit->record('volunteer.approved', $volunteer, null, ['user_id' => $user->id], $approver);

            return $user;
        });

        SendTransactionalSms::dispatch((string) $volunteer->phone_encrypted, 'Welcome! Your volunteer application with '.config('app.name').' has been approved. Sign in at '.route('login').' with this number.', 'volunteer.approved');

        return $volunteer->refresh()->setRelation('user', $user);
    }

    public function reject(Volunteer $volunteer, User $approver, string $reason): void
    {
        $this->assertCanDecide($volunteer, $approver);

        if ($volunteer->status !== VolunteerStatus::Applied) {
            throw ValidationException::withMessages(['status' => __('volunteers.not_pending')]);
        }

        $this->transition($volunteer, VolunteerStatus::Rejected, $approver, $reason);
    }

    public function suspend(Volunteer $volunteer, User $by, string $reason): void
    {
        $this->assertCanManage($volunteer, $by);

        if ($volunteer->status !== VolunteerStatus::Approved) {
            throw ValidationException::withMessages(['status' => __('volunteers.not_active')]);
        }

        DB::transaction(function () use ($volunteer, $by, $reason): void {
            if ($user = $volunteer->loadMissing('user')->user) {
                $user->activeAssignments()->whereHas('role', fn ($q) => $q->where('slug', 'volunteer'))
                    ->update(['revoked_at' => now(), 'revoked_by' => $by->id]);

                // Suspend the account only when volunteering was the person's only role.
                if ($user->activeAssignments()->doesntExist()) {
                    $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspended_by' => $by->id, 'suspension_reason' => 'Volunteer suspended: '.$reason])->save();
                    $this->sessions->revokeAllSessions($user, by: $by);
                }
                $user->flushAuthorizationCache();
            }

            $this->transition($volunteer, VolunteerStatus::Suspended, $by, $reason);
        });
    }

    public function reactivate(Volunteer $volunteer, User $by): void
    {
        $this->assertCanManage($volunteer, $by);

        if ($volunteer->status !== VolunteerStatus::Suspended) {
            throw ValidationException::withMessages(['status' => __('volunteers.not_suspended')]);
        }

        DB::transaction(function () use ($volunteer, $by): void {
            if ($user = $volunteer->loadMissing('user')->user) {
                if ($user->status === UserStatus::Suspended) {
                    $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
                }
                $this->grantVolunteerRole($user, (int) $volunteer->ward_id, $by);
            }

            $this->transition($volunteer, VolunteerStatus::Approved, $by, 'Reactivated');
        });
    }

    /** Moves an approved volunteer (and their role assignment) to another ward in the actor's scope. */
    public function reassign(Volunteer $volunteer, Ward $ward, User $by): void
    {
        $this->assertCanManage($volunteer, $by);

        if (! $by->geoScope('volunteers.manage')->covers(ScopeType::Ward, $ward->id)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        DB::transaction(function () use ($volunteer, $ward, $by): void {
            $from = $volunteer->loadMissing('ward', 'user')->ward?->name;
            $volunteer->forceFill(['ward_id' => $ward->id, 'lga_id' => $ward->lga_id, 'polling_unit_id' => null, 'version' => $volunteer->version + 1])->save();

            if ($volunteer->user && $volunteer->status === VolunteerStatus::Approved) {
                $volunteer->user->activeAssignments()->whereHas('role', fn ($q) => $q->where('slug', 'volunteer'))
                    ->update(['revoked_at' => now(), 'revoked_by' => $by->id]);
                $this->grantVolunteerRole($volunteer->user, $ward->id, $by);
            }

            $this->audit->record('volunteer.reassigned', $volunteer, ['ward' => ['from' => $from, 'to' => $ward->name]], [], $by);
        });
    }

    /** @param list<string> $skills @param list<string> $availability */
    public function updateProfile(Volunteer $volunteer, array $skills, array $availability, User $by): void
    {
        $this->assertCanManage($volunteer, $by);

        $volunteer->forceFill([
            'skills' => array_values(array_intersect($skills, array_column(VolunteerSkill::cases(), 'value'))),
            'availability' => array_values(array_intersect($availability, array_column(Availability::cases(), 'value'))),
            'version' => $volunteer->version + 1,
        ])->save();
        $this->audit->recordChanges('volunteer.updated', $volunteer);
    }

    private function grantVolunteerRole(User $user, int $wardId, User $by): void
    {
        $role = Role::query()->where('slug', 'volunteer')->firstOrFail();

        $exists = $user->activeAssignments()->where('role_id', $role->id)->where('scope_type', ScopeType::Ward)->where('scope_id', $wardId)->exists();

        if (! $exists) {
            RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::Ward, 'scope_id' => $wardId, 'assigned_by' => $by->id]);
            $this->audit->record('user.role_assigned', $user, null, ['role' => 'volunteer', 'scope' => 'ward:'.$wardId, 'via' => 'volunteer approval'], $by);
        }

        $user->flushAuthorizationCache();
    }

    private function transition(Volunteer $volunteer, VolunteerStatus $to, User $by, ?string $reason): void
    {
        $from = $volunteer->status;
        $volunteer->forceFill(['status' => $to, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_reason' => $reason, 'version' => $volunteer->version + 1])->save();
        WorkflowTransition::record($volunteer, $from->value, $to->value, $by, $reason);
        $this->audit->record('volunteer.'.$to->value, $volunteer, null, $reason ? ['reason' => $reason] : [], $by);
    }

    private function assertCanDecide(Volunteer $volunteer, User $by): void
    {
        if (! $by->hasPermission('volunteers.approve') || ! $volunteer->isWithin($by->geoScope('volunteers.approve'))) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }

    private function assertCanManage(Volunteer $volunteer, User $by): void
    {
        if (! $by->hasPermission('volunteers.manage') || ! $volunteer->isWithin($by->geoScope('volunteers.manage'))) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
