<?php

declare(strict_types=1);

namespace App\Services\Election;

/**
 * Arithmetic and consistency checks on figures copied from a result sheet.
 * Problems are recorded with the submission, never silently corrected.
 */
final class ResultValidator
{
    /**
     * @param  array{registered_voters?: int|null, accredited_voters: int, valid_votes: int, rejected_votes: int, total_votes_cast: int}  $figures
     * @param  array<string, int>  $scores
     * @param  list<string>  $partyCodes
     * @return list<array{code: string, message: string}>
     */
    public function check(array $figures, array $scores, array $partyCodes): array
    {
        $issues = [];
        $add = function (string $code, array $params = []) use (&$issues): void {
            $issues[] = ['code' => $code, 'message' => __('election.issue_'.$code, $params)];
        };

        $unknown = array_diff(array_keys($scores), $partyCodes);
        if ($unknown !== []) {
            $add('unknown_party', ['parties' => implode(', ', $unknown)]);
        }
        foreach ($scores as $code => $votes) {
            if (! is_int($votes) || $votes < 0) {
                $add('negative', ['field' => $code]);
            }
        }

        $sum = array_sum(array_map('intval', $scores));
        if ($sum !== $figures['valid_votes']) {
            $add('sum_valid', ['sum' => number_format($sum), 'valid' => number_format($figures['valid_votes'])]);
        }
        if ($figures['total_votes_cast'] !== $figures['valid_votes'] + $figures['rejected_votes']) {
            $add('total_cast', ['valid' => number_format($figures['valid_votes']), 'rejected' => number_format($figures['rejected_votes']), 'total' => number_format($figures['total_votes_cast'])]);
        }
        if ($figures['total_votes_cast'] > $figures['accredited_voters']) {
            $add('over_voting', ['total' => number_format($figures['total_votes_cast']), 'accredited' => number_format($figures['accredited_voters'])]);
        }
        $registered = $figures['registered_voters'] ?? null;
        if ($registered !== null && $figures['accredited_voters'] > $registered) {
            $add('over_accreditation', ['accredited' => number_format($figures['accredited_voters']), 'registered' => number_format($registered)]);
        }

        return $issues;
    }
}
