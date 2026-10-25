<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Incident triage by coordinators (incidents.manage) within their area. */
final class IncidentService
{
    public function __construct(private readonly AuditLogger $audit, private readonly EvidenceStore $evidence) {}

    public function canView(Incident $incident, User $user): bool
    {
        return $user->hasPermission('incidents.view') && ($incident->lga_id === null ? $user->geoScope('incidents.view')->statewide : $incident->isWithin($user->geoScope('incidents.view')));
    }

    public function canManage(Incident $incident, User $user): bool
    {
        return $user->hasPermission('incidents.manage') && ($incident->lga_id === null ? $user->geoScope('incidents.manage')->statewide : $incident->isWithin($user->geoScope('incidents.manage')));
    }

    public function acknowledge(Incident $incident, User $by): void
    {
        $this->move($incident, [IncidentStatus::Reported], IncidentStatus::Acknowledged, $by, null, ['assigned_to' => $incident->assigned_to ?? $by->id]);
    }

    public function escalate(Incident $incident, User $by, string $note): void
    {
        $this->move($incident, [IncidentStatus::Reported, IncidentStatus::Acknowledged], IncidentStatus::Escalated, $by, $note);
    }

    public function resolve(Incident $incident, User $by, string $resolution): void
    {
        $this->move($incident, [IncidentStatus::Reported, IncidentStatus::Acknowledged, IncidentStatus::Escalated], IncidentStatus::Resolved, $by, $resolution,
            ['resolution' => mb_substr($resolution, 0, 2000), 'resolved_by' => $by->id, 'resolved_at' => now()]);
    }

    public function dismiss(Incident $incident, User $by, string $reason): void
    {
        $this->move($incident, [IncidentStatus::Reported, IncidentStatus::Acknowledged], IncidentStatus::Dismissed, $by, $reason,
            ['resolution' => mb_substr($reason, 0, 2000), 'resolved_by' => $by->id, 'resolved_at' => now()]);
    }

    public function reopen(Incident $incident, User $by, string $reason): void
    {
        $this->move($incident, [IncidentStatus::Resolved, IncidentStatus::Dismissed], IncidentStatus::Acknowledged, $by, $reason, ['resolved_at' => null]);
    }

    public function addEvidence(Incident $incident, User $by, UploadedFile $file): void
    {
        if (! $this->canManage($incident, $by) && $incident->reported_by !== $by->id) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        $this->evidence->store($file, $incident, $by);
    }

    /**
     * @param  list<IncidentStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function move(Incident $incident, array $from, IncidentStatus $to, User $by, ?string $note = null, array $extra = []): void
    {
        if (! $this->canManage($incident, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        if (! in_array($incident->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('content.cannot_move', ['from' => $incident->status->label(), 'to' => $to->label()])]);
        }

        DB::transaction(function () use ($incident, $to, $by, $note, $extra): void {
            $previous = $incident->status;
            $incident->forceFill(['status' => $to, 'version' => $incident->version + 1] + $extra)->save();
            WorkflowTransition::record($incident, $previous->value, $to->value, $by, $note);
            $this->audit->record('incident.'.$to->value, $incident, null, $note ? ['note' => mb_substr($note, 0, 300)] : [], $by);
        });
    }
}
