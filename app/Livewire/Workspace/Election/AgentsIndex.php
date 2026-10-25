<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Livewire\Concerns\WithTableState;
use App\Models\AgentAssignment;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\User;
use App\Services\Election\AgentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Polling units in the viewer's area with their agents. Coordinators assign and withdraw agents here. */
final class AgentsIndex extends Component
{
    use WithTableState;

    #[Url(except: '')]
    public string $filter = '';

    #[Locked]
    public ?int $unitId = null;

    public string $name = '';

    public string $phone = '';

    public string $role = 'primary';

    #[Locked]
    public ?int $withdrawId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('polling-agents.view');
    }

    protected function sortableColumns(): array
    {
        return ['code', 'name'];
    }

    public function startAssign(int $unitId): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'phone');
        $this->unitId = $unitId;
        $this->role = AgentAssignment::query()->where('election_id', Election::current()?->id)->where('polling_unit_id', $unitId)->where('status', 'assigned')->where('role', 'primary')->exists() ? 'backup' : 'primary';
        $this->dispatch('open-modal', name: 'assign-agent');
    }

    public function assign(AgentService $agents): void
    {
        $this->validate(['name' => ['required', 'string', 'min:2', 'max:150'], 'phone' => ['required', 'string', 'max:20'], 'role' => [Rule::in(['primary', 'backup'])]],
            [], ['name' => __('public.full_name'), 'phone' => __('public.phone')]);
        $election = Election::current();
        if ($election === null || $this->unitId === null) {
            return;
        }

        try {
            $agents->assign($this->actor(), $election, PollingUnit::query()->findOrFail($this->unitId), $this->name, $this->phone, $this->role);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? collect($e->errors()));

            return;
        } catch (AuthorizationDenied $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        $this->dispatch('close-modal', name: 'assign-agent');
        $this->dispatch('toast', type: 'success', title: __('election.agent_assigned'));
    }

    public function startWithdraw(int $assignmentId): void
    {
        $this->withdrawId = $assignmentId;
        $this->reset('reason');
        $this->dispatch('open-modal', name: 'withdraw-agent');
    }

    public function withdraw(AgentService $agents): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:300']], [], ['reason' => __('volunteers.reason')]);
        try {
            $agents->withdraw($this->actor(), AgentAssignment::query()->findOrFail($this->withdrawId), $this->reason);
        } catch (AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }
        $this->dispatch('close-modal', name: 'withdraw-agent');
        $this->dispatch('toast', type: 'success', title: __('election.agent_withdrawn'));
    }

    public function render(): View
    {
        $election = Election::current();
        $user = $this->actor();
        $active = fn ($q) => $q->where('election_id', $election?->id)->where('status', 'assigned');

        $units = PollingUnit::query()->visibleTo($user, 'polling-agents.view')->with(['ward:id,name', 'lga:id,name'])
            ->with(['agentAssignments' => fn ($q) => $active($q)->with('user:id,name,phone_last4')->orderBy('role')])
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'ilike', $this->likeTerm())->orWhere('name', 'ilike', $this->likeTerm())))
            ->when($this->filter === 'unassigned', fn ($q) => $q->whereDoesntHave('agentAssignments', $active))
            ->when($this->filter === 'not_arrived', fn ($q) => $q->whereHas('agentAssignments', fn ($q) => $active($q)->whereNull('arrived_at')))
            ->when($this->filter === 'arrived', fn ($q) => $q->whereHas('agentAssignments', fn ($q) => $active($q)->whereNotNull('arrived_at')));

        return view('livewire.workspace.election.agents-index', [
            'election' => $election,
            'units' => $this->applySort($units, 'code', 'asc')->paginate($this->perPage),
            'canManage' => $user->hasPermission('polling-agents.manage'),
            'assignUnit' => $this->unitId ? PollingUnit::query()->find($this->unitId) : null,
        ])->title(__('election.agents'))->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.agents') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
