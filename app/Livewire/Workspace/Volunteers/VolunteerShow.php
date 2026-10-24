<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Volunteers;

use App\Enums\Availability;
use App\Enums\VolunteerSkill;
use App\Models\ContactAttempt;
use App\Models\Lga;
use App\Models\Task;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Models\WorkflowTransition;
use App\Services\Volunteers\TrainingService;
use App\Services\Volunteers\VolunteerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class VolunteerShow extends Component
{
    #[Locked]
    public Volunteer $volunteer;

    public string $reason = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    /** @var list<string> */
    public array $skills = [];

    /** @var list<string> */
    public array $availability = [];

    public function mount(Volunteer $volunteer): void
    {
        $this->authorize('volunteers.view');
        abort_unless($volunteer->isWithin($this->actor()->geoScope('volunteers.view')), 404);

        $this->volunteer = $volunteer;
        $this->skills = $volunteer->skills;
        $this->availability = $volunteer->availability;
        $this->lgaId = $volunteer->lga_id;
    }

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function approve(VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.approve');
        $volunteers->approve($this->volunteer, $this->actor());
        $this->volunteer->refresh();
        $this->dispatch('toast', type: 'success', title: __('volunteers.approved_ok', ['name' => $this->volunteer->full_name]), message: __('volunteers.approved_help'));
    }

    /** reject | suspend */
    public function decide(string $action, VolunteerService $volunteers): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => __('volunteers.reason')]);

        match ($action) {
            'reject' => $volunteers->reject($this->volunteer, $this->actor(), $this->reason),
            'suspend' => $volunteers->suspend($this->volunteer, $this->actor(), $this->reason),
            default => abort(400),
        };

        $this->reset('reason');
        $this->volunteer->refresh();
        $this->dispatch('close-modal', name: 'volunteer-'.$action);
        $this->dispatch('toast', type: 'success', title: __('volunteers.'.$action.'ed_ok', ['name' => $this->volunteer->full_name]));
    }

    public function reactivate(VolunteerService $volunteers): void
    {
        $volunteers->reactivate($this->volunteer, $this->actor());
        $this->volunteer->refresh();
        $this->dispatch('toast', type: 'success', title: __('volunteers.reactivated_ok', ['name' => $this->volunteer->full_name]));
    }

    public function reassign(VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.manage');
        $this->validate(['wardId' => ['required', 'integer']], [], ['wardId' => __('volunteers.ward')]);
        $ward = Ward::query()->visibleTo($this->actor(), 'volunteers.manage')->findOrFail($this->wardId);
        $volunteers->reassign($this->volunteer, $ward, $this->actor());

        $this->volunteer->refresh();
        $this->dispatch('close-modal', name: 'volunteer-reassign');
        $this->dispatch('toast', type: 'success', title: __('volunteers.reassigned_ok', ['ward' => $ward->name]));
    }

    public function saveProfile(VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.manage');
        $this->validate([
            'skills.*' => [Rule::enum(VolunteerSkill::class)],
            'availability.*' => [Rule::enum(Availability::class)],
        ]);
        $volunteers->updateProfile($this->volunteer, $this->skills, $this->availability, $this->actor());

        $this->volunteer->refresh();
        $this->dispatch('close-modal', name: 'volunteer-profile');
        $this->dispatch('toast', type: 'success', title: __('ui.saved'));
    }

    public function render(TrainingService $training): View
    {
        $volunteer = $this->volunteer->load(['lga:id,name', 'ward:id,name', 'user', 'decider:id,name']);
        $user = $volunteer->user;
        $scope = $this->actor()->geoScope('volunteers.manage');

        return view('livewire.workspace.volunteers.volunteer-show', [
            'history' => WorkflowTransition::query()->where('subject_type', $volunteer->getMorphClass())->where('subject_id', $volunteer->id)->with('user:id,name')->latest('id')->get(),
            'results' => $user ? $training->bestResults($user) : collect(),
            'modules' => TrainingModule::query()->whereNotNull('published_at')->whereIn('audience', ['all', 'volunteer'])->orderBy('sort')->get(['id', 'title', 'pass_mark']),
            'stats' => $user ? [
                'open_tasks' => Task::query()->open()->where('assigned_to', $user->id)->count(),
                'completed_tasks' => Task::query()->where('assigned_to', $user->id)->where('status', 'completed')->count(),
                'contacts' => ContactAttempt::query()->where('user_id', $user->id)->count(),
            ] : null,
            'lgas' => $this->actor()->can('volunteers.manage') ? Lga::query()->whereIn('id', Ward::query()->visibleTo($scope)->select('lga_id'))->orderBy('name')->pluck('name', 'id') : collect(),
            'wards' => $this->lgaId ? Ward::query()->visibleTo($scope)->where('lga_id', $this->lgaId)->whereKeyNot((int) $volunteer->ward_id)->orderBy('name')->pluck('name', 'id') : collect(),
            'skillOptions' => VolunteerSkill::options(),
            'availabilityOptions' => Availability::options(),
        ])->title($volunteer->full_name)->layoutData(['breadcrumbs' => [__('nav.groups.people') => null, __('volunteers.title') => route('workspace.volunteers.index'), $volunteer->full_name => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
