<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Projects;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Projects\ProjectService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ProjectShow extends Component
{
    #[Locked]
    public Project $project;

    public string $note = '';

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()?->canAny(['projects.manage', 'projects.approve']), 403);
        $this->project = $project;
    }

    public function submit(ProjectService $projects): void
    {
        $this->act(fn () => $projects->submit($this->project, $this->actor()), __('content.submitted'));
    }

    public function publish(ProjectService $projects): void
    {
        $this->act(fn () => $projects->publish($this->project, $this->actor()), __('content.published_ok'));
    }

    public function reject(ProjectService $projects): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->act(fn () => $projects->reject($this->project, $this->actor(), $this->note), __('content.rejected_ok'));
        $this->dispatch('close-modal', name: 'project-note');
    }

    public function withdraw(ProjectService $projects): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->act(fn () => $projects->withdraw($this->project, $this->actor(), $this->note), __('content.withdrawn_ok'));
        $this->dispatch('close-modal', name: 'project-withdraw');
    }

    private function act(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return;
        }

        $this->project->refresh();
        $this->reset('note');
        $this->dispatch('toast', type: 'success', title: $success);
    }

    public function render(): View
    {
        $project = $this->project->load(['lga:id,name', 'ward:id,name', 'cover', 'creator:id,name', 'approver:id,name']);

        return view('livewire.workspace.projects.project-show', [
            'history' => WorkflowTransition::query()->where('subject_type', $project->getMorphClass())->where('subject_id', $project->id)->with('user:id,name')->latest('id')->get(),
            'isAuthor' => $project->created_by === $this->actor()->id,
        ])->title($project->title)->layoutData(['breadcrumbs' => [__('content.title') => route('workspace.content.index'), __('projects.title') => route('workspace.projects.index'), $project->title => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
