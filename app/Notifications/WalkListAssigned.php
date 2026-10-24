<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WalkList;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class WalkListAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly WalkList $list) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'walk_list.assigned',
            'icon' => 'map-pinned',
            'title' => __('canvassing.notify_assigned', ['name' => $this->list->name]),
            'body' => null,
            'url' => route('workspace.canvassing.show', $this->list, false),
        ];
    }
}
