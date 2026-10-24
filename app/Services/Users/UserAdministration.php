<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\OtpPurpose;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Jobs\SendTransactionalSms;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authentication\OtpService;
use App\Services\Authentication\SignInService;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Account lifecycle and role assignment, with anti-escalation rules:
 * an actor may only grant roles whose permissions they hold themselves, at the
 * role's geographic level, inside their own scope; privileged roles need
 * roles.assign-privileged.
 */
final class UserAdministration
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SignInService $sessions,
        private readonly OtpService $otp,
    ) {}

    public function create(User $actor, string $name, string $phone, Role $role, ScopeType $scopeType, ?int $scopeId): User
    {
        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);

        if (User::query()->where('phone_hash', PhoneHasher::hash($e164))->exists()) {
            throw ValidationException::withMessages(['phone' => 'An account already exists for this number.']);
        }

        $this->assertCanAssign($actor, $role, $scopeType, $scopeId);

        $user = DB::transaction(function () use ($actor, $name, $e164, $role, $scopeType, $scopeId): User {
            $user = new User(['name' => $name]);
            $user->setPhone($e164);
            $user->save();

            $assignment = $this->persistAssignment($actor, $user, $role, $scopeType, $scopeId);
            $this->audit->record('user.created', $user, ['name' => $name], ['role' => $role->slug, 'scope' => $assignment->scopeLabel()]);

            return $user;
        });

        SendTransactionalSms::dispatch(
            $e164,
            'You have been given access to '.config('app.name').' as '.$role->name.'. Sign in at '.route('login').' with this number.',
            'account.created',
        );

        return $user;
    }

    public function assign(User $actor, User $user, Role $role, ScopeType $scopeType, ?int $scopeId): RoleAssignment
    {
        $this->assertCanManage($actor, $user);
        $this->assertCanAssign($actor, $role, $scopeType, $scopeId);

        $exists = $user->activeAssignments()->where('role_id', $role->id)->where('scope_type', $scopeType)
            ->where(fn ($q) => $scopeId === null ? $q->whereNull('scope_id') : $q->where('scope_id', $scopeId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['role' => 'This person already has that role for that area.']);
        }

        return DB::transaction(function () use ($actor, $user, $role, $scopeType, $scopeId): RoleAssignment {
            $assignment = $this->persistAssignment($actor, $user, $role, $scopeType, $scopeId);
            $this->audit->record('user.role_assigned', $user, null, ['role' => $role->slug, 'scope' => $assignment->scopeLabel(), 'assignment_id' => $assignment->id]);
            $user->flushAuthorizationCache();

            return $assignment;
        });
    }

    public function revoke(User $actor, RoleAssignment $assignment): void
    {
        $this->assertCanManage($actor, $assignment->user);
        $this->assertCanAssign($actor, $assignment->role, $assignment->scope_type, $assignment->scope_id);

        if ($assignment->user->is($actor) && $assignment->role->slug === 'super_admin'
            && RoleAssignment::query()->whereNull('revoked_at')->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->count() === 1) {
            throw ValidationException::withMessages(['role' => 'The last super administrator cannot remove their own role.']);
        }

        DB::transaction(function () use ($actor, $assignment): void {
            $assignment->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            $this->audit->record('user.role_revoked', $assignment->user, null, ['role' => $assignment->role->slug, 'scope' => $assignment->scopeLabel(), 'assignment_id' => $assignment->id]);
        });

        // Permissions change immediately: sessions are re-authorised on every request.
        $assignment->user->flushAuthorizationCache();
    }

    public function suspend(User $actor, User $user, string $reason): void
    {
        $this->assertCanManage($actor, $user);

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['reason' => 'You cannot suspend your own account.']);
        }

        DB::transaction(function () use ($actor, $user, $reason): void {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                'suspended_at' => now(),
                'suspended_by' => $actor->id,
                'suspension_reason' => mb_substr($reason, 0, 500),
            ])->save();
            $this->audit->record('user.suspended', $user, null, ['reason' => $reason]);
        });

        $this->sessions->revokeAllSessions($user, by: $actor);
    }

    public function reactivate(User $actor, User $user): void
    {
        $this->assertCanManage($actor, $user);

        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
        $this->audit->record('user.reactivated', $user);
    }

    /** Account recovery, step 1: send a confirmation code to the person's new number. */
    public function beginPhoneChange(User $actor, User $user, string $newPhone): OtpChallenge
    {
        $this->assertCanManage($actor, $user);
        $e164 = NigerianPhoneNumber::normalise($newPhone) ?? throw ValidationException::withMessages(['newPhone' => __('validation.nigerian_mobile')]);

        if (User::query()->where('phone_hash', PhoneHasher::hash($e164))->exists()) {
            throw ValidationException::withMessages(['newPhone' => 'That number already belongs to an account.']);
        }

        return $this->otp->request($e164, OtpPurpose::PhoneChange, request()->ip(), $user);
    }

    /** Account recovery, step 2: the code from the new number confirms the change. */
    public function completePhoneChange(User $actor, User $user, OtpChallenge $challenge, string $newPhone, string $code): void
    {
        $this->assertCanManage($actor, $user);
        $e164 = (string) NigerianPhoneNumber::normalise($newPhone);

        if ($challenge->purpose !== OtpPurpose::PhoneChange || $challenge->user_id !== $user->id
            || $challenge->phone_hash !== PhoneHasher::hash($e164) || ! $this->otp->verify($challenge, $code)) {
            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        $old = $user->phone;

        DB::transaction(function () use ($user, $e164): void {
            $user->setPhone($e164)->save();
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'pin_hash' => null])->save();
            $this->audit->record('user.phone_changed', $user, null, ['new_last4' => substr($e164, -4)]);
        });

        $this->sessions->revokeAllSessions($user, by: $actor);

        if ($old) {
            SendTransactionalSms::dispatch($old, 'The phone number on your '.config('app.name').' account was changed by an administrator. If you did not ask for this, contact them immediately.', 'security.phone_changed');
        }
    }

    public function canManage(User $actor, User $user): bool
    {
        if (! $actor->hasPermission('users.manage')) {
            return false;
        }

        if ($user->requiresTwoFactor() && ! $actor->hasPermission('roles.assign-privileged')) {
            return false;
        }

        return User::query()->whereKey($user->id)->visibleTo($actor, 'users.manage')->exists();
    }

    public function canAssign(User $actor, Role $role, ScopeType $scopeType, ?int $scopeId): bool
    {
        try {
            $this->assertCanAssign($actor, $role, $scopeType, $scopeId);

            return true;
        } catch (AuthorizationDenied|ValidationException) {
            return false;
        }
    }

    /**
     * Roles the actor may grant, i.e. whose permissions they hold.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function assignableRoles(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->hasPermission('roles.assign')) {
            return collect();
        }

        return Role::query()->with('permissions')->orderBy('name')->get()->filter(function (Role $role) use ($actor): bool {
            if ($role->is_privileged && ! $actor->hasPermission('roles.assign-privileged')) {
                return false;
            }

            if ($role->slug === 'super_admin') {
                return $actor->hasRole('super_admin');
            }

            return collect($role->permissionSlugs())->every(fn (string $slug): bool => $actor->hasPermission($slug));
        })->values();
    }

    private function assertCanManage(User $actor, User $user): void
    {
        if (! $this->canManage($actor, $user)) {
            throw new AuthorizationDenied('You cannot manage this account.');
        }
    }

    private function assertCanAssign(User $actor, Role $role, ScopeType $scopeType, ?int $scopeId): void
    {
        if (! $this->assignableRoles($actor)->contains('id', $role->id)) {
            throw new AuthorizationDenied('You cannot assign this role.');
        }

        if ($scopeType !== $role->scope_level) {
            throw ValidationException::withMessages(['scope' => "{$role->name} is assigned at {$role->scope_level->label()} level."]);
        }

        if ($scopeType !== ScopeType::State && $scopeId === null) {
            throw ValidationException::withMessages(['scope' => 'Choose the area for this role.']);
        }

        if (! $actor->geoScope('roles.assign')->covers($scopeType, $scopeId)) {
            throw new AuthorizationDenied('That area is outside your own scope.');
        }
    }

    private function persistAssignment(User $actor, User $user, Role $role, ScopeType $scopeType, ?int $scopeId): RoleAssignment
    {
        return RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeType === ScopeType::State ? null : $scopeId,
            'assigned_by' => $actor->id,
        ]);
    }
}
