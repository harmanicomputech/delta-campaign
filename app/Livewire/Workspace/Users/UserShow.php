<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Users;

use App\Enums\ScopeType;
use App\Livewire\Concerns\PicksScope;
use App\Models\AuditLog;
use App\Models\OtpChallenge;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Authentication\SignInService;
use App\Services\Users\AuthorizationDenied;
use App\Services\Users\UserAdministration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class UserShow extends Component
{
    use PicksScope;

    #[Locked]
    public User $user;

    public ?int $roleId = null;

    public string $suspensionReason = '';

    public string $newPhone = '';

    public string $phoneCode = '';

    #[Locked]
    public ?string $phoneChallengeId = null;

    public function mount(User $user): void
    {
        $this->authorize('users.view');
        abort_unless(User::query()->whereKey($user->id)->visibleTo($this->actor())->exists(), 404);
        $this->user = $user;
    }

    public function updatedRoleId(): void
    {
        $this->resetScopePicker();
    }

    public function assignRole(UserAdministration $admin): void
    {
        $role = $admin->assignableRoles($this->actor())->firstWhere('id', $this->roleId);

        if ($role === null) {
            $this->addError('roleId', __('users.cannot_assign'));

            return;
        }

        $scopeId = $this->selectedScopeId($role->scope_level);
        if ($role->scope_level !== ScopeType::State && $scopeId === null) {
            $this->addError('scope', __('users.choose_area'));

            return;
        }

        $this->attempt(fn () => $admin->assign($this->actor(), $this->user, $role, $role->scope_level, $scopeId), function (): void {
            $this->reset('roleId');
            $this->resetScopePicker();
            $this->dispatch('close-modal', name: 'assign-role');
            $this->dispatch('toast', type: 'success', title: __('users.role_assigned'));
        });
    }

    public function revokeAssignment(int $assignmentId, UserAdministration $admin): void
    {
        $assignment = $this->user->activeAssignments()->with('role')->findOrFail($assignmentId);

        $this->attempt(fn () => $admin->revoke($this->actor(), $assignment), fn () => $this->dispatch('toast', type: 'success', title: __('users.role_revoked')));
    }

    public function suspend(UserAdministration $admin): void
    {
        $this->validate(['suspensionReason' => ['required', 'string', 'min:5', 'max:500']], [], ['suspensionReason' => 'reason']);

        $this->attempt(fn () => $admin->suspend($this->actor(), $this->user, $this->suspensionReason), function (): void {
            $this->reset('suspensionReason');
            $this->dispatch('close-modal', name: 'suspend-user');
            $this->dispatch('toast', type: 'success', title: __('users.suspended'));
        });
    }

    public function reactivate(UserAdministration $admin): void
    {
        $this->attempt(fn () => $admin->reactivate($this->actor(), $this->user), fn () => $this->dispatch('toast', type: 'success', title: __('users.reactivated')));
    }

    public function signOutEverywhere(UserAdministration $admin, SignInService $sessions): void
    {
        abort_unless($admin->canManage($this->actor(), $this->user), 403);
        $count = $sessions->revokeAllSessions($this->user, by: $this->actor());
        $this->dispatch('toast', type: 'success', title: trans_choice('account.sessions_ended', $count, ['count' => $count]));
    }

    public function sendPhoneCode(UserAdministration $admin): void
    {
        $this->validate(['newPhone' => ['required', 'string', new \App\Rules\NigerianMobileNumber]], [], ['newPhone' => 'new number']);

        $this->attempt(function () use ($admin): void {
            $this->phoneChallengeId = $admin->beginPhoneChange($this->actor(), $this->user, $this->newPhone)->id;
        }, fn () => $this->dispatch('toast', type: 'info', title: __('users.code_sent_new_number')));
    }

    public function confirmPhoneChange(UserAdministration $admin): void
    {
        $this->validate(['phoneCode' => ['required', 'string', 'max:10']], [], ['phoneCode' => 'code']);
        $challenge = OtpChallenge::query()->find($this->phoneChallengeId);

        if ($challenge === null) {
            $this->addError('phoneCode', __('auth.code_expired'));

            return;
        }

        $this->attempt(fn () => $admin->completePhoneChange($this->actor(), $this->user, $challenge, $this->newPhone, $this->phoneCode), function (): void {
            $this->reset('newPhone', 'phoneCode', 'phoneChallengeId');
            $this->dispatch('close-modal', name: 'change-phone');
            $this->dispatch('toast', type: 'success', title: __('users.phone_changed'));
        });
    }

    public function render(UserAdministration $admin): View
    {
        $actor = $this->actor();
        $this->user->refresh()->flushAuthorizationCache();
        $assignable = $admin->assignableRoles($actor);
        $selectedRole = $assignable->firstWhere('id', $this->roleId);
        $scope = $actor->geoScope('roles.assign');

        return view('livewire.workspace.users.user-show', [
            'assignments' => $this->user->activeAssignments()->with(['role', 'assigner'])->get(),
            'canManage' => $admin->canManage($actor, $this->user),
            'canRevoke' => fn (RoleAssignment $a): bool => $admin->canAssign($actor, $a->role, $a->scope_type, $a->scope_id),
            'assignable' => $assignable->pluck('name', 'id'),
            'scopeType' => $selectedRole?->scope_level->value,
            'districts' => $this->districtOptions($scope),
            'lgas' => $this->lgaOptions($scope),
            'wards' => $this->wardOptions($scope),
            'history' => $this->user->loginEvents()->latest('created_at')->limit(15)->get(),
            'audit' => $actor->can('audit.view')
                ? AuditLog::query()->where('subject_type', $this->user->getMorphClass())->where('subject_id', (string) $this->user->id)->with('actor')->latest('id')->limit(15)->get()
                : collect(),
            'isSelf' => $actor->is($this->user),
        ])->title($this->user->name)->layoutData(['breadcrumbs' => [
            __('users.title') => route('workspace.users.index'),
            $this->user->name => null,
        ]]);
    }

    private function attempt(callable $action, callable $onSuccess): void
    {
        try {
            $action();
        } catch (AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'danger', title: __('users.not_allowed'), message: $e->getMessage());

            return;
        }

        $onSuccess();
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
