<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\ScopeType;
use App\Enums\TaskEventType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Lga;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\User;
use App\Models\Ward;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskEscalated;
use App\Notifications\TaskOverdue;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\GeoScope;
use App\Services\Supporters\StaleRecord;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Field tasks with area scoping, history, comments, hashed evidence files,
 * reassignment, reminders and escalation to coordinators covering the area.
 */
final class TaskService
{
    public const array EVIDENCE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title: string, description?: string|null, priority?: string, due_at?: \DateTimeInterface|null, lga_id?: int|null, ward_id?: int|null}  $data
     */
    public function create(User $by, array $data, ?User $assignee = null): Task
    {
        $ward = empty($data['ward_id']) ? null : Ward::query()->findOrFail($data['ward_id']);
        $lga = $ward ? Lga::query()->find($ward->lga_id) : (empty($data['lga_id']) ? null : Lga::query()->findOrFail($data['lga_id']));
        $scope = $by->geoScope('tasks.manage');

        $inScope = match (true) {
            ! $by->hasPermission('tasks.manage') => false,
            $ward !== null => $scope->covers(ScopeType::Ward, $ward->id),
            $lga !== null => $scope->covers(ScopeType::Lga, $lga->id),
            default => $scope->isStatewide(),
        };

        if (! $inScope) {
            throw new AuthorizationDenied(__('tasks.outside_area'));
        }

        return DB::transaction(function () use ($by, $data, $assignee, $ward, $lga): Task {
            $task = Task::query()->create([
                'title' => mb_substr($data['title'], 0, 200),
                'description' => $data['description'] ?? null,
                'priority' => TaskPriority::tryFrom($data['priority'] ?? 'normal') ?? TaskPriority::Normal,
                'status' => TaskStatus::Open,
                'due_at' => $data['due_at'] ?? null,
                'created_by' => $by->id,
                'lga_id' => $lga?->id,
                'ward_id' => $ward?->id,
            ]);

            $this->event($task, TaskEventType::Created, $by);
            $this->audit->record('task.created', $task, null, ['priority' => $task->priority->value], $by);

            if ($assignee !== null) {
                $this->assign($task->refresh(), $assignee, $by);
            }

            return $task->refresh();
        });
    }

    /**
     * A task raised by the platform itself (USSD callback requests, SMS field reports). No
     * person is the creator; the area's coordinators see it and assign it.
     *
     * @param  array{title: string, description?: string|null, priority?: string, due_at?: \DateTimeInterface|null, lga_id?: int|null, ward_id?: int|null, polling_unit_id?: int|null}  $data
     */
    public function createSystem(array $data, string $source): Task
    {
        return DB::transaction(function () use ($data, $source): Task {
            $task = Task::query()->create([
                'title' => mb_substr($data['title'], 0, 200),
                'description' => $data['description'] ?? null,
                'priority' => TaskPriority::tryFrom($data['priority'] ?? 'normal') ?? TaskPriority::Normal,
                'status' => TaskStatus::Open,
                'due_at' => $data['due_at'] ?? null,
                'lga_id' => $data['lga_id'] ?? null,
                'ward_id' => $data['ward_id'] ?? null,
                'polling_unit_id' => $data['polling_unit_id'] ?? null,
            ]);

            $this->event($task, TaskEventType::Created, null, ['source' => $source]);
            $this->audit->as($source, fn () => $this->audit->record('task.created', $task, null, ['source' => $source]));

            return $task->refresh();
        });
    }

    public function assign(Task $task, User $assignee, User $by): void
    {
        $this->assertCanManage($task, $by);

        if (! $this->canBeAssignedTo($task, $assignee)) {
            throw ValidationException::withMessages(['assignee' => __('tasks.assignee_not_in_area')]);
        }

        $previous = $task->assigned_to;
        $task->forceFill(['assigned_to' => $assignee->id, 'version' => $task->version + 1])->save();
        $this->event($task, TaskEventType::Assigned, $by, ['from' => $previous, 'to' => $assignee->id, 'to_name' => $assignee->name]);
        $this->audit->record($previous ? 'task.reassigned' : 'task.assigned', $task, null, ['to' => $assignee->id], $by);

        if ($assignee->id !== $by->id) {
            $assignee->notify(new TaskAssigned($task));
        }
    }

    public function changeStatus(Task $task, TaskStatus $to, User $by, int $expectedVersion, ?string $note = null): Task
    {
        $this->assertCanWork($task, $by);

        if ($to === TaskStatus::Cancelled) {
            $this->assertCanManage($task, $by);
        }

        if ($to === TaskStatus::Completed && $task->assigned_to !== $by->id && ! $this->canManage($task, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        return DB::transaction(function () use ($task, $to, $by, $expectedVersion, $note): Task {
            $fresh = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($fresh->version !== $expectedVersion) {
                throw new StaleRecord;
            }

            $from = $fresh->status;
            $fresh->forceFill([
                'status' => $to,
                'completed_at' => $to === TaskStatus::Completed ? now() : null,
                'completion_note' => $to === TaskStatus::Completed ? $note : $fresh->completion_note,
                'version' => $fresh->version + 1,
            ])->save();

            $this->event($fresh, TaskEventType::StatusChanged, $by, ['from' => $from->value, 'to' => $to->value, 'note' => $note]);

            return $fresh;
        });
    }

    public function comment(Task $task, User $by, string $body): void
    {
        $this->assertCanWork($task, $by);
        $task->comments()->create(['user_id' => $by->id, 'body' => mb_substr($body, 0, 2000)]);
        $this->event($task, TaskEventType::Commented, $by);
    }

    public function addEvidence(Task $task, User $by, UploadedFile $file): TaskEvidence
    {
        $this->assertCanWork($task, $by);

        if (! in_array($file->getMimeType(), self::EVIDENCE_MIMES, true)) {
            throw ValidationException::withMessages(['evidence' => __('tasks.evidence_type')]);
        }

        $disk = (string) config('campaign.privacy.exports_disk');
        $uuid = (string) Str::uuid();
        $path = "task-evidence/{$task->uuid}/{$uuid}.".($file->guessExtension() ?: 'bin');
        $hash = (string) hash_file('sha256', $file->getRealPath());

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        $evidence = TaskEvidence::query()->create([
            'uuid' => $uuid,
            'task_id' => $task->id,
            'user_id' => $by->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => $hash,
            'created_at' => now(),
        ]);

        $this->event($task, TaskEventType::EvidenceAdded, $by, ['file' => $evidence->original_name, 'sha256' => $hash]);
        $this->audit->record('task.evidence_added', $task, null, ['sha256' => $hash], $by);

        return $evidence;
    }

    public function changeDue(Task $task, ?\DateTimeInterface $dueAt, User $by): void
    {
        $this->assertCanManage($task, $by);
        $old = $task->due_at?->toIso8601String();
        $task->forceFill(['due_at' => $dueAt, 'escalation_level' => 0, 'last_reminded_at' => null, 'version' => $task->version + 1])->save();
        $this->event($task, TaskEventType::DueChanged, $by, ['from' => $old, 'to' => $dueAt?->format(DATE_ATOM)]);
    }

    /**
     * Hourly: reminds assignees of overdue tasks (at most daily) and escalates tasks overdue past
     * their priority's grace period to the coordinators covering the task's area.
     *
     * @return array{reminded: int, escalated: int}
     */
    public function processOverdue(): array
    {
        $counts = ['reminded' => 0, 'escalated' => 0];

        Task::query()->open()->whereNotNull('due_at')->where('due_at', '<', now())->with('assignee')
            ->chunkById(200, function ($tasks) use (&$counts): void {
                foreach ($tasks as $task) {
                    if ($task->assignee && ($task->last_reminded_at === null || $task->last_reminded_at->lt(now()->subDay()))) {
                        $task->assignee->notify(new TaskOverdue($task));
                        $task->forceFill(['last_reminded_at' => now()])->save();
                        $this->event($task, TaskEventType::Reminded, null, ['to' => $task->assigned_to]);
                        $counts['reminded']++;
                    }

                    $graceHours = $task->priority->escalateAfterHours() * ($task->escalation_level + 1);
                    if ($task->escalation_level < 2 && $task->due_at->copy()->addHours($graceHours)->isPast()) {
                        $this->escalate($task, null, 'Overdue');
                        $counts['escalated']++;
                    }
                }
            });

        return $counts;
    }

    /** Escalates to the coordinators one level up. $by is null when the scheduler escalates. */
    public function escalate(Task $task, ?User $by, string $reason): void
    {
        if ($by !== null) {
            $this->assertCanWork($task, $by);
        }

        $recipients = $this->escalationRecipients($task)->reject(fn (User $u) => $u->id === $task->assigned_to);

        $task->forceFill(['escalation_level' => $task->escalation_level + 1, 'escalated_at' => now()])->save();
        $this->event($task, TaskEventType::Escalated, $by, ['level' => $task->escalation_level, 'reason' => $reason, 'notified' => $recipients->count()]);
        $this->audit->as($by ?? 'task-scheduler', fn () => $this->audit->record('task.escalated', $task, null, ['level' => $task->escalation_level, 'reason' => $reason]));

        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskEscalated($task, $reason));
        }
    }

    /**
     * Managers of the task's area at the next level up: ward → LGA → district → statewide.
     *
     * @return Collection<int, User>
     */
    public function escalationRecipients(Task $task): Collection
    {
        $levels = array_filter([
            ['scope_type' => 'ward', 'scope_id' => $task->ward_id],
            ['scope_type' => 'lga', 'scope_id' => $task->lga_id],
            ['scope_type' => 'district', 'scope_id' => $task->senatorial_district_id],
            ['scope_type' => 'state', 'scope_id' => null],
        ], fn ($l) => $l['scope_type'] === 'state' || $l['scope_id'] !== null);

        // Skip as many levels as the task has already been escalated.
        $levels = array_slice(array_values($levels), $task->escalation_level);

        foreach ($levels as $level) {
            $users = User::query()->where('status', 'active')
                ->whereHas('activeAssignments', fn ($q) => $q->where('scope_type', $level['scope_type'])
                    ->when($level['scope_id'], fn ($w) => $w->where('scope_id', $level['scope_id']), fn ($w) => $w->whereNull('scope_id'))
                    ->whereHas('role.permissions', fn ($p) => $p->where('slug', 'tasks.manage')))
                ->get();

            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        return collect();
    }

    public function recordEvidenceDownload(TaskEvidence $evidence, User $by): void
    {
        $this->audit->record('task.evidence_downloaded', $evidence->task, null, ['evidence' => $evidence->uuid, 'sha256' => $evidence->sha256], $by);
    }

    public function canManage(Task $task, User $user): bool
    {
        return $user->hasPermission('tasks.manage') && $this->within($task, $user->geoScope('tasks.manage'));
    }

    public function canWork(Task $task, User $user): bool
    {
        return ($task->assigned_to === $user->id && $user->hasPermission('tasks.work')) || $this->canManage($task, $user);
    }

    public function canView(Task $task, User $user): bool
    {
        return $task->assigned_to === $user->id || $task->created_by === $user->id
            || ($user->hasPermission('tasks.view') && $this->within($task, $user->geoScope('tasks.view')));
    }

    public function canBeAssignedTo(Task $task, User $assignee): bool
    {
        return $assignee->isActive() && $assignee->hasPermission('tasks.work') && $this->within($task, $assignee->geoScope('tasks.work'));
    }

    private function within(Task $task, GeoScope $scope): bool
    {
        // Statewide tasks (no area) are visible only to statewide scopes.
        return $task->lga_id === null ? $scope->isStatewide() : $task->isWithin($scope);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function event(Task $task, TaskEventType $type, ?User $by, ?array $data = null): void
    {
        $task->events()->create(['user_id' => $by?->id, 'type' => $type, 'data' => $data, 'created_at' => now()]);
    }

    private function assertCanManage(Task $task, User $by): void
    {
        if (! $this->canManage($task, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }

    private function assertCanWork(Task $task, User $by): void
    {
        if (! $this->canWork($task, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
