<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MessageCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class CampaignDecided extends Notification
{
    use Queueable;

    public function __construct(public readonly MessageCampaign $campaign, public readonly bool $approved) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'messaging.decided',
            'icon' => $this->approved ? 'circle-check' : 'circle-x',
            'title' => __($this->approved ? 'messaging.notify_approved' : 'messaging.notify_rejected', ['name' => $this->campaign->name]),
            'body' => $this->approved ? null : $this->campaign->decision_note,
            'url' => route('workspace.messaging.show', $this->campaign, false),
        ];
    }
}
