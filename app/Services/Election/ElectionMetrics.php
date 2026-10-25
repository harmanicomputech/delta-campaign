<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Enums\IncidentStatus;
use App\Enums\ResultStatus;
use App\Models\AgentAssignment;
use App\Models\Election;
use App\Models\Incident;
use App\Models\OfficialResult;
use App\Models\PollingUnit;
use App\Models\ResultSubmission;
use App\Services\Authorization\GeoScope;
use Illuminate\Support\Facades\DB;

/**
 * Election-day figures for a person's area. Everything is counted from
 * records; campaign-verified tallies are always labelled as such and kept
 * apart from official figures.
 */
final class ElectionMetrics
{
    /**
     * @return array{units: int, assigned: int, arrived: int, checklist: int, results: int, verified: int, flagged: int, official: int,
     *               incidents_open: int, incidents_critical: int, by_severity: array<string, int>}
     */
    public function summary(Election $election, GeoScope $scope): array
    {
        $assignments = AgentAssignment::query()->where('election_id', $election->id)->where('status', 'assigned')->visibleTo($scope);
        $submissions = ResultSubmission::query()->where('election_id', $election->id)->visibleTo($scope)->whereNotIn('status', [ResultStatus::Superseded, ResultStatus::Rejected]);
        $open = Incident::query()->where('election_id', $election->id)->visibleTo($scope)->whereIn('status', [IncidentStatus::Reported, IncidentStatus::Acknowledged, IncidentStatus::Escalated]);

        $bySeverity = (clone $open)->toBase()->selectRaw('severity, count(*) as n')->groupBy('severity')->pluck('n', 'severity')->map(fn ($n) => (int) $n)->all();

        return [
            'units' => PollingUnit::query()->visibleTo($scope)->count(),
            'assigned' => (clone $assignments)->distinct()->count('polling_unit_id'),
            'arrived' => (clone $assignments)->whereNotNull('arrived_at')->distinct()->count('polling_unit_id'),
            'checklist' => (clone $assignments)->whereNotNull('checklist_completed_at')->distinct()->count('polling_unit_id'),
            'results' => (clone $submissions)->distinct()->count('polling_unit_id'),
            'verified' => (clone $submissions)->where('status', ResultStatus::Verified)->distinct()->count('polling_unit_id'),
            'flagged' => (clone $submissions)->where('status', ResultStatus::Flagged)->distinct()->count('polling_unit_id'),
            'official' => OfficialResult::query()->where('election_id', $election->id)->visibleTo($scope)->count(),
            'incidents_open' => array_sum($bySeverity),
            'incidents_critical' => $bySeverity['critical'] ?? 0,
            'by_severity' => $bySeverity,
        ];
    }

    /**
     * Coverage by LGA (state/district viewers) or by ward (LGA viewers).
     *
     * @return list<array{id: int, name: string, units: int, assigned: int, arrived: int, results: int, verified: int, incidents: int}>
     */
    public function breakdown(Election $election, GeoScope $scope): array
    {
        // Leaders see LGAs; LGA and ward coordinators see their wards.
        $byWard = ! $scope->statewide && $scope->districtIds === [];
        $column = $byWard ? 'ward_id' : 'lga_id';
        $table = $byWard ? 'wards' : 'lgas';

        $count = fn ($query, string $expr = 'count(*)') => $query->toBase()->selectRaw("{$column} as area, {$expr} as n")->groupBy($column)->pluck('n', 'area');

        $units = $count(PollingUnit::query()->visibleTo($scope));
        $assigned = $count(AgentAssignment::query()->where('election_id', $election->id)->where('status', 'assigned')->visibleTo($scope), 'count(distinct polling_unit_id)');
        $arrived = $count(AgentAssignment::query()->where('election_id', $election->id)->where('status', 'assigned')->whereNotNull('arrived_at')->visibleTo($scope), 'count(distinct polling_unit_id)');
        $results = $count(ResultSubmission::query()->where('election_id', $election->id)->whereNotIn('status', ['superseded', 'rejected'])->visibleTo($scope), 'count(distinct polling_unit_id)');
        $verified = $count(ResultSubmission::query()->where('election_id', $election->id)->where('status', 'verified')->visibleTo($scope), 'count(distinct polling_unit_id)');
        $incidents = $count(Incident::query()->where('election_id', $election->id)->whereIn('status', ['reported', 'acknowledged', 'escalated'])->visibleTo($scope));

        return DB::table($table)->whereIn('id', $units->keys())->orderBy('name')->get(['id', 'name'])->map(fn ($a) => [
            'id' => (int) $a->id, 'name' => (string) $a->name, 'units' => (int) ($units[$a->id] ?? 0), 'assigned' => (int) ($assigned[$a->id] ?? 0),
            'arrived' => (int) ($arrived[$a->id] ?? 0), 'results' => (int) ($results[$a->id] ?? 0), 'verified' => (int) ($verified[$a->id] ?? 0),
            'incidents' => (int) ($incidents[$a->id] ?? 0),
        ])->all();
    }

    /**
     * Sum of campaign-verified sheets in the area, by party. NOT an official result.
     *
     * @return array{units: int, scores: array<string, int>, valid: int, rejected: int}
     */
    public function verifiedTally(Election $election, GeoScope $scope): array
    {
        return $this->tally(ResultSubmission::query()->where('election_id', $election->id)->where('status', ResultStatus::Verified)->visibleTo($scope)->get(['scores', 'valid_votes', 'rejected_votes']));
    }

    /** @return array{units: int, scores: array<string, int>, valid: int, rejected: int} */
    public function officialTally(Election $election, GeoScope $scope): array
    {
        return $this->tally(OfficialResult::query()->where('election_id', $election->id)->visibleTo($scope)->get(['scores', 'valid_votes', 'rejected_votes']));
    }

    /**
     * Polling units where the verified sheet and the official figures differ.
     *
     * @return list<int> polling unit ids
     */
    public function discrepantUnits(Election $election, GeoScope $scope): array
    {
        $official = OfficialResult::query()->where('election_id', $election->id)->visibleTo($scope)->get()->keyBy('polling_unit_id');
        if ($official->isEmpty()) {
            return [];
        }

        return ResultSubmission::query()->where('election_id', $election->id)->where('status', ResultStatus::Verified)->whereIn('polling_unit_id', $official->keys())->get()
            ->filter(fn (ResultSubmission $s) => app(ResultReview::class)->compare($s, $official[$s->polling_unit_id])['status'] === 'differs')
            ->pluck('polling_unit_id')->values()->all();
    }

    /**
     * @param  iterable<ResultSubmission|OfficialResult>  $rows
     * @return array{units: int, scores: array<string, int>, valid: int, rejected: int}
     */
    private function tally(iterable $rows): array
    {
        $scores = [];
        $units = $valid = $rejected = 0;
        foreach ($rows as $row) {
            $units++;
            $valid += $row->valid_votes;
            $rejected += $row->rejected_votes;
            foreach ($row->scores as $code => $votes) {
                $scores[$code] = ($scores[$code] ?? 0) + (int) $votes;
            }
        }
        arsort($scores);

        return compact('units', 'scores', 'valid', 'rejected');
    }
}
