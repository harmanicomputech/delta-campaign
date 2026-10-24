<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Users;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Livewire\Concerns\PicksScope;
use App\Livewire\Concerns\WithTableState;
use App\Models\Role;
use App\Models\User;
use App\Rules\NigerianMobileNumber;
use App\Services\Users\AuthorizationDenied;
use App\Services\Users\UserAdministration;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

final class UsersIndex extends Component
{
    use PicksScope, WithTableState;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $role = '';

    // Create form
    public string $name = '';

    public string $phone = '';

    public ?int $roleId = null;

    public function mount(): void
    {
        $this->authorize('users.view');
    }

    protected function sortableColumns(): array
    {
        return ['name', 'last_login_at', 'created_at'];
    }

    public function updatedRoleId(): void
    {
        $this->resetScopePicker();
    }

    public function create(UserAdministration $admin): void
    {
        $this->authorize('users.manage');

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', new NigerianMobileNumber],
            'roleId' => ['required', 'integer'],
        ], [], ['roleId' => 'role']);

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

        try {
            $user = $admin->create($this->actor(), $this->name, $this->phone, $role, $role->scope_level, $scopeId);
        } catch (AuthorizationDenied $e) {
            $this->addError('scope', $e->getMessage());

            return;
        }

        $this->reset('name', 'phone', 'roleId');
        $this->resetScopePicker();
        $this->dispatch('close-modal', name: 'create-user');
        $this->dispatch('toast', type: 'success', title: __('users.created', ['name' => $user->name]));
    }

    public function render(UserAdministration $admin): View
    {
        $actor = $this->actor();
        $term = trim($this->search);
        $phoneHash = $term !== '' && preg_match('/\d{7,}/', $term) ? PhoneHasher::fromInput($term) : null;

        $users = User::query()
            ->visibleTo($actor)
            ->with('activeAssignments.role')
            ->when($term !== '', fn (Builder $q) => $phoneHash
                ? $q->where('phone_hash', $phoneHash)
                : $q->where('name', 'ilike', $this->likeTerm()))
            ->when(in_array($this->status, ['active', 'suspended'], true), fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->role !== '', fn (Builder $q) => $q->whereHas('activeAssignments.role', fn (Builder $r) => $r->where('slug', $this->role)));

        $assignable = $actor->can('users.manage') ? $admin->assignableRoles($actor) : collect();
        $selectedRole = $assignable->firstWhere('id', $this->roleId);
        $scope = $actor->geoScope('roles.assign');

        return view('livewire.workspace.users.users-index', [
            'users' => $this->applySort($users, 'name')->paginate($this->perPage),
            'roles' => Role::query()->orderBy('name')->pluck('name', 'slug'),
            'assignable' => $assignable->pluck('name', 'id'),
            'scopeType' => $selectedRole?->scope_level->value,
            'districts' => $this->districtOptions($scope),
            'lgas' => $this->lgaOptions($scope),
            'wards' => $this->wardOptions($scope),
            'statuses' => UserStatus::cases(),
        ])->title(__('users.title'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('users.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
