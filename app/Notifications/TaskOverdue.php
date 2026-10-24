<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TaskOverdue extends Notification
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
            'kind' => 'task.overdue',
            'icon' => 'clock',
            'title' => __('tasks.notify_overdue', ['title' => $this->task->title]),
            'body' => null,
            'url' => route('workspace.tasks.show', $this->task, false),
        ];
    }
}
