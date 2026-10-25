<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MessageCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class CampaignAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(public readonly MessageCampaign $campaign) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'messaging.awaiting_approval',
            'icon' => 'send',
            'title' => __('messaging.notify_awaiting', ['name' => $this->campaign->name]),
            'body' => trans_choice('messaging.recipients_count', $this->campaign->estimated_recipients, ['count' => number_format($this->campaign->estimated_recipients)]),
            'url' => route('workspace.messaging.show', $this->campaign, false),
        ];
    }
}
