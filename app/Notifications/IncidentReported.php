<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class IncidentReported extends Notification
{
    use Queueable;

    public function __construct(public readonly Incident $incident) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $incident = $this->incident->loadMissing('pollingUnit:id,code,name');

        return [
            'kind' => 'incident.reported',
            'icon' => 'triangle-alert',
            'title' => __('election.notify_incident', ['severity' => $incident->severity->label(), 'category' => $incident->category->label()]),
            'body' => $incident->reference.' · '.($incident->pollingUnit?->code ?? ''),
            'url' => route('workspace.election.incidents.show', $incident, false),
        ];
    }
}
