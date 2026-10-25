<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Projects;

use App\Enums\ProjectStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

final class ProjectsIndex extends Component
{
    use WithTableState;

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAny(['projects.manage', 'projects.approve']), 403);
    }

    protected function sortableColumns(): array
    {
        return ['title', 'updated_at'];
    }

    public function render(): View
    {
        $query = Project::query()->with(['lga:id,name', 'creator:id,name'])
            ->when(ProjectStatus::tryFrom($this->status), fn ($q, ProjectStatus $s) => $q->where('status', $s))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'ilike', $this->likeTerm()));

        return view('livewire.workspace.projects.projects-index', [
            'projects' => $this->applySort($query, 'updated_at', 'desc')->paginate($this->perPage),
            'statuses' => ProjectStatus::options(),
        ])->title(__('projects.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('content.title') => route('workspace.content.index'), __('projects.title') => null]]);
    }
}
