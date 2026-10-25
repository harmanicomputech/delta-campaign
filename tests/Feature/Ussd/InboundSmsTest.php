<?php

declare(strict_types=1);

namespace Tests\Feature\Ussd;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Livewire\Workspace\Tasks\TasksIndex;
use App\Models\InboundSms;
use App\Models\PollingUnit;
use App\Models\Task;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class InboundSmsTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private const string PHONE = '+2348059990001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
    }

    private function sms(string $text, string $from = self::PHONE, ?string $id = null): TestResponse
    {
        return $this->post(route('webhooks.sms.inbound', ['key' => 'test-webhook-secret']), ['from' => $from, 'to' => '12345', 'text' => $text, 'date' => now()->toDateTimeString(), 'id' => $id ?? uniqid('ATXid_')]);
    }

    private function lastReplyTo(string $e164): ?string
    {
        $messages = array_values(array_filter(OutboxSmsGateway::messages(), fn (array $m) => $m['to'] === $e164));

        return $messages === [] ? null : $messages[0]['message']; // The outbox lists the newest first.
    }

    public function test_stop_suppresses_every_channel_and_start_re_subscribes(): void
    {
        $supporter = $this->registerSupporter('08059990001', consents: ['sms' => true, 'whatsapp' => true]);
        $consent = app(ConsentService::class);

        $this->sms('stop please')->assertOk();
        $this->assertFalse($consent->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertFalse($consent->isEligible($supporter->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates));
        $this->assertStringContainsString('no longer receive', (string) $this->lastReplyTo(self::PHONE));

        $this->sms('START');
        $this->assertTrue($consent->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertSame(['opted_out', 'opted_in'], InboundSms::query()->orderBy('id')->pluck('result')->all());
    }

    public function test_provider_retries_are_processed_once(): void
    {
        $this->sms('HELP', id: 'ATXid_same');
        $this->sms('HELP', id: 'ATXid_same');

        $this->assertSame(1, InboundSms::query()->count());
        $this->assertSame(1, $this->messagesTo(self::PHONE));
    }

    public function test_messages_are_stored_hashed_and_encrypted(): void
    {
        $this->sms('HELP me with something private');

        $row = DB::table('inbound_sms')->first();
        $this->assertSame(PhoneHasher::hash(self::PHONE), $row->phone_hash);
        $this->assertStringNotContainsString('private', (string) $row->text_encrypted);
        $this->assertStringNotContainsString('8059990001', json_encode($row));
    }

    public function test_unknown_text_from_unknown_numbers_gets_no_reply(): void
    {
        $this->sms('hello there');

        $this->assertSame('ignored', InboundSms::query()->sole()->status);
        $this->assertSame(0, $this->messagesTo(self::PHONE));
    }

    public function test_field_reports_from_staff_become_tasks_for_the_area(): void
    {
        $ward = $this->ward('udu');
        PollingUnit::query()->create(['ward_id' => $ward->id, 'code' => 'SMP-UDU-009', 'name' => 'Sample Town Hall', 'is_sample' => true]);
        $volunteer = User::factory()->withRole('volunteer', $ward->id)->phone(self::PHONE)->create(['name' => 'Sample Field Volunteer']);
        $coordinator = User::factory()->withRole('ward_coordinator', $ward->id)->create();

        $this->sms('report smp-udu-009 Voting materials arrived late, queue is long');

        $task = Task::query()->sole();
        $this->assertSame('high', $task->priority->value);
        $this->assertSame($ward->id, $task->ward_id);
        $this->assertStringContainsString('Voting materials arrived late', (string) $task->description);
        $this->assertStringContainsString('Report received', (string) $this->lastReplyTo(self::PHONE));
        $this->assertSame($volunteer->id, InboundSms::query()->sole()->user_id);

        Livewire::actingAs($coordinator)->test(TasksIndex::class, ['view' => 'area'])->assertSee('SMS report from Sample Field Volunteer');
    }

    public function test_reports_are_refused_from_strangers_outside_the_area_or_badly_formatted(): void
    {
        PollingUnit::query()->create(['ward_id' => $this->ward('sapele')->id, 'code' => 'SMP-SAP-001', 'name' => 'Sample Sapele Hall', 'is_sample' => true]);

        $this->sms('REPORT SMP-SAP-001 Something happened');
        $this->assertStringContainsString('registered campaign staff', (string) $this->lastReplyTo(self::PHONE));

        User::factory()->withRole('volunteer', $this->ward('udu')->id)->phone(self::PHONE)->create();
        $this->sms('REPORT SMP-SAP-001 Something happened');
        $this->assertStringContainsString('outside your assigned area', (string) $this->lastReplyTo(self::PHONE));
        $this->sms('REPORT');
        $this->assertStringContainsString('Format: REPORT', (string) $this->lastReplyTo(self::PHONE));
        $this->sms('REPORT NOPE-1 x');
        $this->assertStringContainsString('was not found', (string) $this->lastReplyTo(self::PHONE));

        $this->assertSame(0, Task::query()->count());
    }

    public function test_the_webhook_needs_the_secret(): void
    {
        $this->post(route('webhooks.sms.inbound'), ['from' => self::PHONE, 'text' => 'STOP'])->assertForbidden();
        $this->assertSame(0, InboundSms::query()->count());
    }
}
