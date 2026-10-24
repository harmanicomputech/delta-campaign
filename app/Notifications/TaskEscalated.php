<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TaskEscalated extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task, public readonly string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task.escalated',
            'icon' => 'triangle-alert',
            'title' => __('tasks.notify_escalated', ['title' => $this->task->title]),
            'body' => $this->reason.' · '.$this->task->loadMissing('ward', 'lga')->areaLabel(),
            'url' => route('workspace.tasks.show', $this->task, false),
        ];
    }
}
