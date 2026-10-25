<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Models\Incident;
use App\Models\ResultSubmission;
use App\Models\TurnoutObservation;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use Illuminate\Testing\TestResponse;

final class ElectionSmsTest extends ElectionTestCase
{
    private const string PHONE = '+2348061230001';

    private function sms(string $text, string $from = self::PHONE, ?string $id = null): TestResponse
    {
        return $this->post(route('webhooks.sms.inbound', ['key' => 'test-webhook-secret']), ['from' => $from, 'to' => '12345', 'text' => $text, 'date' => now()->toDateTimeString(), 'id' => $id ?? uniqid('ATXid_')]);
    }

    private function lastReply(string $to = self::PHONE): string
    {
        $messages = array_values(array_filter(OutboxSmsGateway::messages(), fn (array $m) => $m['to'] === $to));

        return $messages[0]['message'] ?? '';
    }

    public function test_agents_report_by_sms_without_typing_the_unit_code(): void
    {
        $assignment = $this->agent($this->unit());

        $this->sms('arrived')->assertOk();
        $this->assertNotNull($assignment->fresh()->arrived_at);
        $this->assertStringContainsString($assignment->pollingUnit->code, $this->lastReply());

        $this->sms('TURNOUT 85')->assertOk();
        $this->assertSame(85, TurnoutObservation::query()->firstOrFail()->accredited_so_far);

        $this->sms('INCIDENT violence Sample fight near the queue')->assertOk();
        $incident = Incident::query()->firstOrFail();
        $this->assertSame('violence', $incident->category->value);
        $this->assertSame('high', $incident->severity->value);
        $this->assertSame('sms', $incident->source);
    }

    public function test_result_by_sms_is_recorded_unverified_and_awaits_a_photo(): void
    {
        $this->agent($this->unit());

        $this->sms('RESULT A=120 V=100 R=5 SPA=60 SPB=40')->assertOk();
        $result = ResultSubmission::query()->firstOrFail();
        $this->assertSame(105, $result->total_votes_cast);
        $this->assertSame(['no_photo'], array_column($result->issues, 'code'));
        $this->assertStringContainsString('unverified', $this->lastReply());

        $this->sms('RESULT A=120 V=100 R=5 SPA=70 SPB=40');
        $this->assertStringContainsString('needs checking', $this->lastReply());
    }

    public function test_provider_retries_do_not_duplicate_reports(): void
    {
        $this->agent($this->unit());
        $this->sms('TURNOUT 40', id: 'ATXid_same');
        $this->sms('TURNOUT 40', id: 'ATXid_same');

        $this->assertSame(1, TurnoutObservation::query()->count());
    }

    public function test_bad_formats_get_help_and_strangers_get_a_refusal(): void
    {
        $this->agent($this->unit());
        $this->sms('RESULT 100 for SPA');
        $this->assertStringContainsString('Format: RESULT', $this->lastReply());
        $this->sms('INCIDENT party something');
        $this->assertStringContainsString('Types:', $this->lastReply());

        $this->sms('ARRIVED', '+2348069999999');
        $this->assertStringContainsString('no polling-unit assignment', $this->lastReply('+2348069999999'));
        $this->assertSame(0, TurnoutObservation::query()->count());
    }

    public function test_help_for_agents_lists_election_commands(): void
    {
        $this->agent($this->unit());
        $this->sms('HELP');
        $this->assertStringContainsString('ARRIVED', $this->lastReply());
    }
}
