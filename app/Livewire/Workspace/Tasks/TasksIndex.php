<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Lga;
use App\Models\Task;
use App\Models\User;
use App\Models\Ward;
use App\Services\Tasks\TaskService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class TasksIndex extends Component
{
    use WithTableState;

    /** mine | area */
    #[Url(except: '')]
    public string $view = '';

    /** open (default) | a TaskStatus value | all */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: false)]
    public bool $overdue = false;

    // New task
    public string $title = '';

    public string $description = '';

    public string $newPriority = 'normal';

    public string $dueAt = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public function mount(): void
    {
        $actor = $this->actor();
        abort_unless($actor->can('tasks.view') || $actor->can('tasks.work'), 403);

        // Managers land on their area; field workers on their own work.
        $allowed = array_keys($this->views());
        if (! in_array($this->view, $allowed, true)) {
            $this->view = $actor->can('tasks.manage') && in_array('area', $allowed, true) ? 'area' : $allowed[0];
        }
    }

    protected function sortableColumns(): array
    {
        return ['due_at', 'title', 'created_at'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'status', 'priority', 'overdue'], true)) {
            $this->resetPage();
        }
        if ($property === 'lgaId') {
            $this->wardId = null;
        }
    }

    public function create(TaskService $tasks): void
    {
        $this->authorize('tasks.manage');
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'newPriority' => [Rule::enum(TaskPriority::class)],
            'dueAt' => ['nullable', 'date', 'after:now'],
            'lgaId' => [$this->actor()->geoScope('tasks.manage')->isStatewide() ? 'nullable' : 'required', 'integer'],
            'wardId' => ['nullable', 'integer'],
        ], [], ['newPriority' => __('tasks.priority'), 'dueAt' => __('tasks.due_at'), 'lgaId' => 'LGA']);

        try {
            $task = $tasks->create($this->actor(), [
                'title' => $this->title,
                'description' => $this->description !== '' ? $this->description : null,
                'priority' => $this->newPriority,
                'due_at' => $this->dueAt !== '' ? Carbon::parse($this->dueAt, config('campaign.display_timezone'))->utc() : null,
                'lga_id' => $this->lgaId,
                'ward_id' => $this->wardId,
            ]);
        } catch (AuthorizationDenied $e) {
            // e.g. a ward coordinator who left the ward empty: the task would cover the whole LGA.
            $this->addError('wardId', $e->getMessage());

            return;
        }

        $this->redirectRoute('workspace.tasks.show', $task);
    }

    /** @return array<string, string> */
    private function views(): array
    {
        return array_filter([
            'mine' => $this->actor()->can('tasks.work') ? __('tasks.mine') : null,
            'area' => $this->actor()->can('tasks.view') ? __('tasks.area') : null,
        ]);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $scope = $actor->geoScope('tasks.manage');
        $base = Task::query()
            ->when($this->view === 'mine', fn (Builder $q) => $q->where('assigned_to', $actor->id))
            ->when($this->view === 'area', fn (Builder $q) => $q->visibleTo($actor, 'tasks.view'));

        $query = (clone $base)->with(['assignee:id,name', 'ward:id,name', 'lga:id,name'])
            ->when($this->status === 'open', fn (Builder $q) => $q->open())
            ->when(TaskStatus::tryFrom($this->status), fn (Builder $q, TaskStatus $s) => $q->where('status', $s))
            ->when(TaskPriority::tryFrom($this->priority), fn (Builder $q, TaskPriority $p) => $q->where('priority', $p))
            ->when($this->overdue, fn (Builder $q) => $q->open()->where('due_at', '<', now()))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('title', 'ilike', $this->likeTerm()));

        if ($this->sort === '') {
            $query->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->orderByRaw('due_at asc nulls last');
        } else {
            $this->applySort($query, 'due_at');
        }

        return view('livewire.workspace.tasks.tasks-index', [
            'views' => $this->views(),
            'tasks' => $query->paginate($this->perPage),
            'counts' => (clone $base)->toBase()->selectRaw("count(*) filter (where status not in ('completed','cancelled')) as open, count(*) filter (where status not in ('completed','cancelled') and due_at < now()) as overdue")->first(),
            'statuses' => ['open' => __('tasks.all_open')] + TaskStatus::options() + ['all' => __('tasks.all_tasks')],
            'priorities' => TaskPriority::options(),
            'statewide' => $scope->isStatewide(),
            'lgas' => $actor->can('tasks.manage') ? Lga::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id')
                ->union(Lga::query()->whereIn('id', Ward::query()->visibleTo($scope)->select('lga_id'))->pluck('name', 'id')) : collect(),
            'wards' => $this->lgaId ? Ward::query()->visibleTo($scope)->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
        ])->title(__('tasks.title'))->layoutData(['breadcrumbs' => [__('nav.groups.field') => null, __('tasks.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
