<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\OfficialSource;
use App\Enums\ResultStatus;
use App\Models\Election;
use App\Models\OfficialResult;
use App\Models\PollingUnit;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Campaign review of submitted results and recording of official figures.
 * Verification means "a second person checked the figures against the photo";
 * it never makes a result official. Official figures are only ever
 * transcribed from an INEC source, which must be cited.
 */
final class ResultReview
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EvidenceStore $evidence,
        private readonly ResultValidator $validator,
    ) {}

    public function canView(ResultSubmission|OfficialResult $result, User $user): bool
    {
        return $user->hasPermission('results.view') && $result->isWithin($user->geoScope('results.view'));
    }

    public function verify(ResultSubmission $submission, User $by): void
    {
        $this->assertVerifier($submission, $by);
        $submission->loadMissing('evidence');
        if ($submission->evidence->isEmpty()) {
            throw ValidationException::withMessages(['status' => __('election.verify_needs_photo')]);
        }
        if (collect($submission->issues)->contains(fn ($i) => in_array($i['code'], ['sum_valid', 'total_cast', 'over_voting', 'over_accreditation', 'unknown_party', 'negative'], true))) {
            throw ValidationException::withMessages(['status' => __('election.verify_has_errors')]);
        }

        DB::transaction(function () use ($submission, $by): void {
            // Only one verified sheet per polling unit.
            $others = ResultSubmission::query()->where('election_id', $submission->election_id)->where('polling_unit_id', $submission->polling_unit_id)
                ->where('status', ResultStatus::Verified)->whereKeyNot($submission->id)->lockForUpdate()->get();
            foreach ($others as $other) {
                $this->move($other, ResultStatus::Superseded, $by, __('election.superseded_by', ['ref' => $submission->reference]));
            }
            $this->move($submission, ResultStatus::Verified, $by, null);
        });
    }

    public function flag(ResultSubmission $submission, User $by, string $note): void
    {
        $this->assertVerifier($submission, $by, allowOwn: true);
        $this->move($submission, ResultStatus::Flagged, $by, $note);
    }

    public function reject(ResultSubmission $submission, User $by, string $note): void
    {
        $this->assertVerifier($submission, $by);
        $this->move($submission, ResultStatus::Rejected, $by, $note);
    }

    /**
     * @param  array{accredited_voters?: int|null, valid_votes: int, rejected_votes: int, total_votes_cast: int}  $figures
     * @param  array<string, int>  $scores
     */
    public function recordOfficial(User $by, Election $election, PollingUnit $unit, OfficialSource $source, string $reference, array $figures, array $scores, ?UploadedFile $document = null): OfficialResult
    {
        if (! $by->hasPermission('results.official') || ! $by->geoScope('results.official')->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        if (trim($reference) === '') {
            throw ValidationException::withMessages(['reference' => __('election.source_required')]);
        }

        $codes = $election->loadMissing('parties')->partyCodes();
        $scores = array_change_key_case($scores, CASE_UPPER);
        foreach ($codes as $code) {
            $scores[$code] = (int) ($scores[$code] ?? 0);
        }
        $issues = $this->validator->check(['accredited_voters' => $figures['accredited_voters'] ?? $figures['total_votes_cast']] + $figures, $scores, $codes);
        if (collect($issues)->contains(fn ($i) => in_array($i['code'], ['unknown_party', 'negative'], true))) {
            throw ValidationException::withMessages(['scores' => $issues[0]['message']]);
        }

        return DB::transaction(function () use ($by, $election, $unit, $source, $reference, $figures, $scores, $document, $issues): OfficialResult {
            $existing = OfficialResult::query()->where('election_id', $election->id)->where('polling_unit_id', $unit->id)->first();
            $official = OfficialResult::query()->updateOrCreate(['election_id' => $election->id, 'polling_unit_id' => $unit->id], [
                'ward_id' => $unit->ward_id, 'lga_id' => $unit->lga_id, 'senatorial_district_id' => $unit->senatorial_district_id,
                'source' => $source, 'source_reference' => mb_substr(trim($reference), 0, 500),
                'accredited_voters' => $figures['accredited_voters'] ?? null, 'valid_votes' => $figures['valid_votes'],
                'rejected_votes' => $figures['rejected_votes'], 'total_votes_cast' => $figures['total_votes_cast'], 'scores' => $scores, 'recorded_by' => $by->id,
            ]);
            if ($document !== null) {
                $this->evidence->store($document, $official, $by);
            }
            // Arithmetic problems in an official sheet are kept as recorded, and noted in the audit trail.
            $this->audit->record($existing ? 'official_result.updated' : 'official_result.recorded', $official,
                $existing ? ['scores' => $existing->scores, 'valid_votes' => $existing->valid_votes] : null,
                ['source' => $source->value, 'reference' => $reference, 'issues' => array_column($issues, 'code')], $by);

            return $official;
        });
    }

    /**
     * Differences between the campaign-verified sheet and the official figures for one polling unit.
     *
     * @return array{status: string, rows: list<array{field: string, verified: int|null, official: int|null, difference: int|null}>}
     */
    public function compare(?ResultSubmission $verified, ?OfficialResult $official): array
    {
        if ($verified === null || $official === null) {
            return ['status' => $official === null ? 'no_official' : 'no_verified', 'rows' => []];
        }

        $rows = [];
        foreach (array_unique(array_merge(array_keys($verified->scores), array_keys($official->scores))) as $code) {
            $rows[] = $this->row((string) $code, $verified->scores[$code] ?? null, $official->scores[$code] ?? null);
        }
        foreach (['valid_votes', 'rejected_votes', 'total_votes_cast', 'accredited_voters'] as $field) {
            $rows[] = $this->row($field, $verified->{$field}, $official->{$field});
        }

        return ['status' => collect($rows)->contains(fn ($r) => $r['difference'] !== null && $r['difference'] !== 0) ? 'differs' : 'matches', 'rows' => $rows];
    }

    /** @return array{field: string, verified: int|null, official: int|null, difference: int|null} */
    private function row(string $field, ?int $verified, ?int $official): array
    {
        return ['field' => $field, 'verified' => $verified, 'official' => $official, 'difference' => $verified !== null && $official !== null ? $official - $verified : null];
    }

    private function assertVerifier(ResultSubmission $submission, User $by, bool $allowOwn = false): void
    {
        if (! $by->hasPermission('results.verify') || ! $submission->isWithin($by->geoScope('results.verify'))) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        if (! $allowOwn && $submission->submitted_by === $by->id) {
            throw new AuthorizationDenied(__('election.not_own_result'));
        }
        if ($submission->status === ResultStatus::Superseded) {
            throw ValidationException::withMessages(['status' => __('election.superseded')]);
        }
    }

    private function move(ResultSubmission $submission, ResultStatus $to, User $by, ?string $note): void
    {
        $previous = $submission->status;
        $submission->forceFill(['status' => $to, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note !== null ? mb_substr($note, 0, 500) : $submission->review_note])->save();
        WorkflowTransition::record($submission, $previous->value, $to->value, $by, $note);
        $this->audit->record('result.'.$to->value, $submission, null, $note ? ['note' => mb_substr($note, 0, 300)] : [], $by);
    }
}
