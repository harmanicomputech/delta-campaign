<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\ResultStatus;
use App\Models\AgentAssignment;
use App\Models\AgentCheckin;
use App\Models\Incident;
use App\Models\ResultSubmission;
use App\Models\TurnoutObservation;
use App\Models\User;
use App\Notifications\IncidentReported;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\AssignableUsers;
use App\Services\Users\AuthorizationDenied;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * What polling agents report from their polling unit: arrival, the opening
 * checklist, turnout counts, incidents and the result sheet. Used by the web
 * app, offline sync and SMS. Every entry may carry a client UUID so retries
 * never create duplicates.
 */
final class FieldReports
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EvidenceStore $evidence,
        private readonly ResultValidator $validator,
    ) {}

    public function arrive(AgentAssignment $assignment, User $by, string $source, ?float $lat = null, ?float $lng = null, ?int $accuracy = null, ?string $clientUuid = null, ?CarbonInterface $at = null): AgentCheckin
    {
        $this->assertReporter($assignment, $by);
        if ($existing = $this->existing(AgentCheckin::class, $clientUuid)) {
            return $existing;
        }

        $distance = null;
        if ($lat !== null && $lng !== null) {
            $distance = $assignment->pollingUnit->distanceTo($lat, $lng);
        }

        return DB::transaction(function () use ($assignment, $by, $source, $lat, $lng, $accuracy, $clientUuid, $at, $distance): AgentCheckin {
            $checkin = AgentCheckin::query()->create([
                'agent_assignment_id' => $assignment->id, 'kind' => 'arrived', 'latitude' => $lat, 'longitude' => $lng,
                'accuracy_m' => $accuracy, 'distance_m' => $distance !== null ? (int) round($distance) : null,
                'source' => $source, 'client_uuid' => $clientUuid, 'occurred_at' => $this->time($at), 'created_at' => now(),
            ]);
            $assignment->arrived_at ??= $checkin->occurred_at;
            $assignment->save();
            $this->audit->record('agent.arrived', $assignment, null, ['source' => $source, 'distance_m' => $checkin->distance_m], $by);

            return $checkin;
        });
    }

    /** @param array<string, bool> $answers keyed by checklist item key */
    public function checklist(AgentAssignment $assignment, User $by, array $answers, string $source, ?string $clientUuid = null): AgentCheckin
    {
        $this->assertReporter($assignment, $by);
        if ($existing = $this->existing(AgentCheckin::class, $clientUuid)) {
            return $existing;
        }

        $keys = array_column($assignment->election->checklist, 'key');
        $clean = [];
        foreach ($keys as $key) {
            $clean[$key] = (bool) ($answers[$key] ?? false);
        }

        $checkin = AgentCheckin::query()->create([
            'agent_assignment_id' => $assignment->id, 'kind' => 'checklist', 'answers' => $clean, 'source' => $source,
            'client_uuid' => $clientUuid, 'occurred_at' => now(), 'created_at' => now(),
        ]);
        $assignment->forceFill(['checklist_completed_at' => now()])->save();
        $this->audit->record('agent.checklist', $assignment, null, ['missing' => array_keys(array_filter($clean, fn ($v) => ! $v))], $by);

        return $checkin;
    }

    public function turnout(AgentAssignment $assignment, User $by, int $count, string $source, ?string $queue = null, ?string $note = null, ?string $clientUuid = null, ?CarbonInterface $at = null): TurnoutObservation
    {
        $this->assertReporter($assignment, $by);
        if ($existing = $this->existing(TurnoutObservation::class, $clientUuid)) {
            return $existing;
        }
        $registered = $assignment->pollingUnit->registered_voters;
        if ($count < 0 || ($registered !== null && $count > $registered)) {
            throw ValidationException::withMessages(['count' => __('election.turnout_range', ['max' => number_format((int) $registered)])]);
        }

        return TurnoutObservation::query()->create([
            'election_id' => $assignment->election_id, 'agent_assignment_id' => $assignment->id, 'polling_unit_id' => $assignment->polling_unit_id,
            'ward_id' => $assignment->ward_id, 'lga_id' => $assignment->lga_id, 'senatorial_district_id' => $assignment->senatorial_district_id,
            'reported_by' => $by->id, 'accredited_so_far' => $count, 'queue' => in_array($queue, ['none', 'short', 'long'], true) ? $queue : null,
            'note' => $note !== null ? mb_substr(trim($note), 0, 500) : null, 'source' => $source, 'client_uuid' => $clientUuid,
            'observed_at' => $this->time($at), 'created_at' => now(),
        ]);
    }

    /** @param list<UploadedFile> $files */
    public function incident(AgentAssignment $assignment, User $by, IncidentCategory $category, IncidentSeverity $severity, string $description, string $source, array $files = [], ?string $clientUuid = null, ?CarbonInterface $at = null): Incident
    {
        $this->assertReporter($assignment, $by);
        if ($existing = $this->existing(Incident::class, $clientUuid)) {
            return $existing;
        }

        $incident = DB::transaction(function () use ($assignment, $by, $category, $severity, $description, $source, $files, $clientUuid, $at): Incident {
            $incident = Incident::query()->create([
                'election_id' => $assignment->election_id, 'polling_unit_id' => $assignment->polling_unit_id, 'ward_id' => $assignment->ward_id,
                'lga_id' => $assignment->lga_id, 'senatorial_district_id' => $assignment->senatorial_district_id, 'reported_by' => $by->id,
                'category' => $category, 'severity' => $severity, 'description' => mb_substr(trim($description), 0, 4000),
                'source' => $source, 'client_uuid' => $clientUuid, 'occurred_at' => $this->time($at),
            ]);
            foreach ($files as $file) {
                $this->evidence->store($file, $incident, $by);
            }
            $this->audit->record('incident.reported', $incident, null, ['severity' => $severity->value, 'category' => $category->value, 'source' => $source], $by);

            return $incident;
        });

        $this->notifyResponders($incident);

        return $incident;
    }

    /**
     * Figures as written on the polling-unit result sheet.
     *
     * @param  array{accredited_voters: int, valid_votes: int, rejected_votes: int, total_votes_cast: int}  $figures
     * @param  array<string, int>  $scores
     * @param  list<UploadedFile>  $files
     */
    public function result(AgentAssignment $assignment, User $by, array $figures, array $scores, string $source, array $files = [], ?string $clientUuid = null): ResultSubmission
    {
        $this->assertReporter($assignment, $by);
        if ($existing = $this->existing(ResultSubmission::class, $clientUuid)) {
            return $existing;
        }

        $election = $assignment->election->loadMissing('parties');
        $codes = $election->partyCodes();
        $scores = array_change_key_case($scores, CASE_UPPER);
        foreach ($codes as $code) {
            $scores[$code] = (int) ($scores[$code] ?? 0);
        }
        $figures['registered_voters'] = $assignment->pollingUnit->registered_voters;

        $issues = $this->validator->check($figures, $scores, $codes);
        if ($files === [] && $source !== 'sms') {
            throw ValidationException::withMessages(['photo' => __('election.photo_required')]);
        }
        if ($files === []) {
            $issues[] = ['code' => 'no_photo', 'message' => __('election.issue_no_photo')];
        }

        return DB::transaction(function () use ($assignment, $by, $figures, $scores, $source, $files, $clientUuid, $issues): ResultSubmission {
            // A new sheet from the same agent replaces their earlier unreviewed one.
            $previous = ResultSubmission::query()->where('election_id', $assignment->election_id)->where('polling_unit_id', $assignment->polling_unit_id)
                ->where('submitted_by', $by->id)->whereIn('status', [ResultStatus::Submitted, ResultStatus::Flagged])->lockForUpdate()->latest('id')->first();

            $others = ResultSubmission::query()->where('election_id', $assignment->election_id)->where('polling_unit_id', $assignment->polling_unit_id)
                ->whereNotIn('status', [ResultStatus::Superseded, ResultStatus::Rejected])->when($previous, fn ($q) => $q->whereKeyNot($previous->id))->get();
            foreach ($others as $other) {
                if ($other->scores != $scores || $other->valid_votes !== $figures['valid_votes'] || $other->rejected_votes !== $figures['rejected_votes']) {
                    $issues[] = ['code' => 'conflict', 'message' => __('election.issue_conflict', ['ref' => $other->reference])];
                }
            }

            $submission = ResultSubmission::query()->create([
                'election_id' => $assignment->election_id, 'polling_unit_id' => $assignment->polling_unit_id, 'ward_id' => $assignment->ward_id,
                'lga_id' => $assignment->lga_id, 'senatorial_district_id' => $assignment->senatorial_district_id, 'submitted_by' => $by->id,
                'source' => $source, 'registered_voters' => $figures['registered_voters'], 'accredited_voters' => $figures['accredited_voters'],
                'valid_votes' => $figures['valid_votes'], 'rejected_votes' => $figures['rejected_votes'], 'total_votes_cast' => $figures['total_votes_cast'],
                'scores' => $scores, 'issues' => $issues, 'status' => $issues === [] ? ResultStatus::Submitted : ResultStatus::Flagged,
                'supersedes_id' => $previous?->id, 'client_uuid' => $clientUuid,
            ]);
            $previous?->forceFill(['status' => ResultStatus::Superseded])->save();

            foreach ($files as $file) {
                $this->evidence->store($file, $submission, $by);
            }
            $this->audit->record('result.submitted', $submission, null, ['source' => $source, 'issues' => array_column($issues, 'code'), 'supersedes' => $previous?->reference], $by);

            return $submission;
        });
    }

    /** Adds a photo to a submission sent earlier (for example by SMS). */
    public function attachPhoto(ResultSubmission $submission, User $by, UploadedFile $file): void
    {
        if ($submission->submitted_by !== $by->id || ! in_array($submission->status, [ResultStatus::Submitted, ResultStatus::Flagged], true)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        $this->evidence->store($file, $submission, $by);
        $issues = array_values(array_filter($submission->issues, fn ($i) => $i['code'] !== 'no_photo'));
        $submission->forceFill(['issues' => $issues, 'status' => $issues === [] ? ResultStatus::Submitted : ResultStatus::Flagged])->save();
    }

    private function assertReporter(AgentAssignment $assignment, User $by): void
    {
        $assignment->loadMissing(['election', 'pollingUnit']);
        if ($assignment->user_id !== $by->id || ! $assignment->isActive() || $assignment->election->status->value !== 'active' || ! $by->hasPermission('election.report')) {
            throw new AuthorizationDenied(__('election.not_your_unit'));
        }
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function existing(string $model, ?string $clientUuid)
    {
        return $clientUuid === null ? null : $model::query()->where('client_uuid', $clientUuid)->first();
    }

    private function time(?CarbonInterface $at): CarbonInterface
    {
        // Offline entries keep their capture time, but never a future one.
        return $at !== null && $at->lte(now()) && $at->gt(now()->subDays(2)) ? $at : now();
    }

    private function notifyResponders(Incident $incident): void
    {
        if (! in_array($incident->severity, [IncidentSeverity::High, IncidentSeverity::Critical], true)) {
            return;
        }
        $responders = app(AssignableUsers::class)->for('incidents.manage', $incident->senatorial_district_id, $incident->lga_id, $incident->ward_id, limit: 25);
        Notification::send($responders, new IncidentReported($incident));
    }
}
