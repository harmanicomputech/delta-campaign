<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task.assigned',
            'icon' => 'list-checks',
            'title' => __('tasks.notify_assigned', ['title' => $this->task->title]),
            'body' => $this->task->due_at ? __('tasks.due', ['date' => $this->task->due_at->timezone(config('campaign.display_timezone'))->format('j M, H:i')]) : null,
            'url' => route('workspace.tasks.show', $this->task, false),
        ];
    }
}
