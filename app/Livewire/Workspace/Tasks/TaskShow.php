<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Authorization\AssignableUsers;
use App\Services\Supporters\StaleRecord;
use App\Services\Tasks\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class TaskShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public Task $task;

    #[Locked]
    public int $loadedVersion = 1;

    public string $comment = '';

    public string $completionNote = '';

    /** @var TemporaryUploadedFile|null */
    public $evidence = null;

    public string $assigneeSearch = '';

    public ?int $assigneeId = null;

    public string $dueAt = '';

    public string $escalationReason = '';

    public function mount(Task $task, TaskService $tasks): void
    {
        abort_unless($tasks->canView($task, $this->actor()), 404);
        $this->task = $task;
        $this->loadedVersion = $task->version;
        $this->dueAt = $task->due_at?->timezone(config('campaign.display_timezone'))->format('Y-m-d\TH:i') ?? '';
    }

    public function setStatus(string $status, TaskService $tasks): void
    {
        $to = TaskStatus::from($status);

        if ($to === TaskStatus::Completed) {
            $this->validate(['completionNote' => ['nullable', 'string', 'max:1000']]);
        }

        try {
            $this->task = $tasks->changeStatus($this->task, $to, $this->actor(), $this->loadedVersion, $to === TaskStatus::Completed && $this->completionNote !== '' ? $this->completionNote : null);
        } catch (StaleRecord) {
            $this->task->refresh();
            $this->loadedVersion = $this->task->version;
            $this->dispatch('toast', type: 'warning', title: __('tasks.stale'));

            return;
        }

        $this->loadedVersion = $this->task->version;
        $this->reset('completionNote');
        $this->dispatch('close-modal', name: 'complete-task');
        $this->dispatch('toast', type: 'success', title: __('tasks.status_changed', ['status' => $to->label()]));
    }

    public function addComment(TaskService $tasks): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:2000']], [], ['comment' => __('tasks.comment')]);
        $tasks->comment($this->task, $this->actor(), $this->comment);
        $this->reset('comment');
    }

    public function updatedEvidence(TaskService $tasks): void
    {
        $this->validate(['evidence' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', TaskService::EVIDENCE_MIMES)]], [], ['evidence' => __('tasks.evidence')]);
        $tasks->addEvidence($this->task, $this->actor(), $this->evidence);
        $this->reset('evidence');
        $this->dispatch('toast', type: 'success', title: __('tasks.evidence_added'));
    }

    public function assign(TaskService $tasks): void
    {
        $this->validate(['assigneeId' => ['required', 'integer']], [], ['assigneeId' => __('tasks.assignee')]);
        $tasks->assign($this->task, User::query()->findOrFail($this->assigneeId), $this->actor());

        $this->task->refresh()->load('assignee:id,name');
        $this->loadedVersion = $this->task->version;
        $this->reset('assigneeId', 'assigneeSearch');
        $this->dispatch('close-modal', name: 'assign-task');
        $this->dispatch('toast', type: 'success', title: __('tasks.assigned_ok', ['name' => $this->task->assignee?->name]));
    }

    public function saveDue(TaskService $tasks): void
    {
        $this->validate(['dueAt' => ['nullable', 'date']], [], ['dueAt' => __('tasks.due_at')]);
        $tasks->changeDue($this->task, $this->dueAt !== '' ? Carbon::parse($this->dueAt, config('campaign.display_timezone'))->utc() : null, $this->actor());

        $this->task->refresh();
        $this->loadedVersion = $this->task->version;
        $this->dispatch('close-modal', name: 'task-due');
        $this->dispatch('toast', type: 'success', title: __('ui.saved'));
    }

    public function escalate(TaskService $tasks): void
    {
        $this->validate(['escalationReason' => ['required', 'string', 'min:3', 'max:300']], [], ['escalationReason' => __('volunteers.reason')]);
        $tasks->escalate($this->task, $this->actor(), $this->escalationReason);

        $this->task->refresh();
        $this->reset('escalationReason');
        $this->dispatch('close-modal', name: 'escalate-task');
        $this->dispatch('toast', type: 'success', title: __('tasks.escalated_ok'));
    }

    public function render(TaskService $tasks, AssignableUsers $assignable): View
    {
        $actor = $this->actor();
        $task = $this->task->load(['assignee:id,name', 'creator:id,name', 'ward:id,name', 'lga:id,name']);
        $canManage = $tasks->canManage($task, $actor);

        // Comments appear in full, so their "commented" events are left out.
        $timeline = $task->events()->where('type', '!=', 'commented')->with('user:id,name')->get()->map(fn ($e) => ['at' => $e->created_at, 'kind' => 'event', 'item' => $e])
            ->concat($task->comments()->with('user:id,name')->get()->map(fn ($c) => ['at' => $c->created_at, 'kind' => 'comment', 'item' => $c]))
            ->sortByDesc(fn ($row) => $row['at']->getTimestamp() * 10 + ($row['kind'] === 'comment' ? 1 : 0))->values();

        return view('livewire.workspace.tasks.task-show', [
            'timeline' => $timeline,
            'evidenceFiles' => $task->evidence()->with('user:id,name')->latest('id')->get(),
            'canManage' => $canManage,
            'canWork' => $tasks->canWork($task, $actor),
            'isAssignee' => $task->assigned_to === $actor->id,
            'candidates' => $canManage ? $assignable->for('tasks.work', $task->senatorial_district_id, $task->lga_id, $task->ward_id, $this->assigneeSearch)
                ->filter(fn (User $u) => $tasks->canBeAssignedTo($task, $u)) : collect(),
        ])->title($task->title)->layoutData(['breadcrumbs' => [__('nav.groups.field') => null, __('tasks.title') => route('workspace.tasks.index'), str($task->title)->limit(40)->toString() => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
