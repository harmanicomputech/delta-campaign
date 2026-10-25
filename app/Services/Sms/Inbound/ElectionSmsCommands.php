<?php

declare(strict_types=1);

namespace App\Services\Sms\Inbound;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Models\User;
use App\Services\Election\AgentService;
use App\Services\Election\FieldReports;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Validation\ValidationException;

/**
 * Structured SMS fallback for polling agents with no data connection
 * (docs/ELECTION_DAY.md):
 *
 *   ARRIVED
 *   TURNOUT <number accredited so far>
 *   INCIDENT <category> <description>
 *   RESULT A=<accredited> V=<valid> R=<rejected> [T=<total cast>] <PARTY>=<votes> …
 *
 * The polling unit comes from the agent's assignment, so codes are never typed.
 */
final class ElectionSmsCommands
{
    public const array KEYWORDS = ['ARRIVED', 'TURNOUT', 'INCIDENT', 'RESULT'];

    /** Short words agents can type for each category. */
    public const array CATEGORY_WORDS = [
        'VIOLENCE' => IncidentCategory::Violence, 'THREAT' => IncidentCategory::Violence, 'INTIMIDATION' => IncidentCategory::Intimidation,
        'BUYING' => IncidentCategory::VoteBuying, 'MATERIALS' => IncidentCategory::Materials, 'BVAS' => IncidentCategory::Technology,
        'LATE' => IncidentCategory::LateOpening, 'PROCEDURE' => IncidentCategory::Procedure, 'ACCESS' => IncidentCategory::Access, 'OTHER' => IncidentCategory::Other,
    ];

    public function __construct(private readonly AgentService $agents, private readonly FieldReports $reports) {}

    /** @return array{0: string, 1: string, 2: string} status, result, reply */
    public function handle(string $keyword, string $text, ?User $user, string $messageId): array
    {
        $assignment = $user !== null && $user->isActive() ? $this->agents->activeAssignmentFor($user) : null;
        if ($assignment === null) {
            return ['ignored', 'election_unassigned', __('sms.election_not_agent')];
        }

        $args = trim((string) preg_replace('/^\S+/u', '', $text));
        $uuid = $this->uuidFor($messageId);

        try {
            return match ($keyword) {
                'ARRIVED' => $this->arrived($assignment, $user, $uuid),
                'TURNOUT' => $this->turnout($assignment, $user, $args, $uuid),
                'INCIDENT' => $this->incident($assignment, $user, $args, $uuid),
                'RESULT' => $this->result($assignment, $user, $args, $uuid),
            };
        } catch (ValidationException $e) {
            return ['failed', strtolower($keyword).'_invalid', mb_substr((string) collect($e->errors())->flatten()->first(), 0, 155)];
        } catch (AuthorizationDenied) {
            return ['failed', 'election_denied', __('sms.election_not_agent')];
        }
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function arrived($assignment, User $user, string $uuid): array
    {
        $this->reports->arrive($assignment, $user, 'sms', clientUuid: $uuid);

        return ['processed', 'election_arrived', __('sms.election_arrived', ['code' => $assignment->pollingUnit->code])];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function turnout($assignment, User $user, string $args, string $uuid): array
    {
        if (preg_match('/^(\d{1,5})\b/', $args, $m) !== 1) {
            return ['failed', 'turnout_format', __('sms.election_turnout_format')];
        }
        $this->reports->turnout($assignment, $user, (int) $m[1], 'sms', clientUuid: $uuid);

        return ['processed', 'election_turnout', __('sms.election_turnout_ok', ['count' => number_format((int) $m[1])])];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function incident($assignment, User $user, string $args, string $uuid): array
    {
        $word = strtoupper((string) strtok($args, " \t\n"));
        $category = self::CATEGORY_WORDS[$word] ?? null;
        $description = trim((string) mb_substr($args, mb_strlen($word)));
        if ($category === null || mb_strlen($description) < 3) {
            return ['failed', 'incident_format', __('sms.election_incident_format')];
        }
        $severity = in_array($category, [IncidentCategory::Violence, IncidentCategory::Intimidation], true) ? IncidentSeverity::High : IncidentSeverity::Medium;
        $incident = $this->reports->incident($assignment, $user, $category, $severity, $description, 'sms', clientUuid: $uuid);

        return ['processed', 'election_incident', __('sms.election_incident_ok', ['ref' => $incident->reference])];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function result($assignment, User $user, string $args, string $uuid): array
    {
        preg_match_all('/([A-Za-z0-9]{1,12})\s*[=:]\s*(\d{1,6})/', $args, $pairs, PREG_SET_ORDER);
        $values = [];
        foreach ($pairs as [, $key, $value]) {
            $values[strtoupper($key)] = (int) $value;
        }
        if (! isset($values['A'], $values['V'], $values['R'])) {
            return ['failed', 'result_format', __('sms.election_result_format')];
        }

        $figures = ['accredited_voters' => $values['A'], 'valid_votes' => $values['V'], 'rejected_votes' => $values['R'], 'total_votes_cast' => $values['T'] ?? $values['V'] + $values['R']];
        $scores = array_diff_key($values, array_flip(['A', 'V', 'R', 'T']));
        $submission = $this->reports->result($assignment, $user, $figures, $scores, 'sms', clientUuid: $uuid);

        return ['processed', 'election_result', $submission->issues === [] || collect($submission->issues)->pluck('code')->all() === ['no_photo']
            ? __('sms.election_result_ok', ['ref' => $submission->reference])
            : __('sms.election_result_issues', ['ref' => $submission->reference, 'issue' => mb_strimwidth($submission->issues[0]['message'], 0, 90, '...')])];
    }

    /** Provider retries of the same message map to the same entry. */
    private function uuidFor(string $messageId): string
    {
        $h = md5('sms:'.$messageId);

        return sprintf('%s-%s-5%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
    }
}
