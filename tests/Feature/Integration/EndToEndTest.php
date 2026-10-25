<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\MessageStatus;
use App\Enums\OfficialSource;
use App\Enums\ResultStatus;
use App\Models\Election;
use App\Models\MessageCampaign;
use App\Models\PollingUnit;
use App\Models\ResultSubmission;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Authorization\GeoScope;
use App\Services\Election\AgentService;
use App\Services\Election\ElectionMetrics;
use App\Services\Election\ElectionSetup;
use App\Services\Election\EvidencePackService;
use App\Services\Election\FieldReports;
use App\Services\Election\ResultReview;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\TemplateService;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

/**
 * Whole journeys across modules, through the same entry points real traffic
 * uses (provider webhooks, services the screens call), ending with the audit
 * chain still intact.
 */
final class EndToEndTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private const string CITIZEN = '+2348051234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->travelTo(now()->timezone('Africa/Lagos')->addDay()->setTime(12, 0)->utc()); // Outside quiet hours.
    }

    private function ussd(string $text, string $session): TestResponse
    {
        return $this->post(route('webhooks.ussd', ['key' => 'test-webhook-secret']), ['sessionId' => $session, 'serviceCode' => '*384*1#', 'phoneNumber' => self::CITIZEN, 'text' => $text]);
    }

    private function sms(string $from, string $text): void
    {
        $this->post(route('webhooks.sms.inbound', ['key' => 'test-webhook-secret']), ['from' => $from, 'to' => '12345', 'text' => $text, 'date' => now()->toDateTimeString(), 'id' => uniqid('ATXid_')])->assertOk();
    }

    public function test_a_citizen_registers_by_ussd_receives_a_campaign_and_opting_out_stops_further_messages(): void
    {
        // 1. Registration on a basic phone.
        $this->ussd('', 's1');
        $this->ussd('1', 's1');
        $this->ussd('1*1', 's1');
        preg_match('/(\d+)\. Aniocha North/', (string) $this->ussd('1*1*Ada Sample', 's1')->getContent(), $m);
        $this->ussd("1*1*Ada Sample*{$m[1]}", 's1');
        $this->ussd("1*1*Ada Sample*{$m[1]}*1", 's1');
        $this->ussd("1*1*Ada Sample*{$m[1]}*1*1", 's1')->assertSee('END Thank you', false);
        $supporter = Supporter::query()->sole();

        // 2. A campaign to the area reaches them.
        $director = User::factory()->withRole('state_director')->withTwoFactor()->create();
        $template = app(TemplateService::class)->save(User::factory()->withRole('super_admin')->create(), [
            'name' => 'Sample update', 'channel' => 'sms', 'purpose' => 'campaign_updates',
            'body_en' => 'Hello {first_name}! Sample update. Reply STOP to opt out.', 'body_pcm' => 'How far {first_name}! Sample update. Reply STOP to stop am.',
        ]);
        $campaigns = app(CampaignService::class);
        $first = $campaigns->saveDraft($director, ['name' => 'Sample one', 'template_id' => $template->id, 'lga_ids' => [$supporter->lga_id]]);
        $campaigns->submit($first, $director);
        $this->assertSame(MessageStatus::Sent, $first->refresh()->messages()->sole()->status);
        $this->assertNotEmpty(array_filter(OutboxSmsGateway::messages(), fn ($s) => $s['to'] === self::CITIZEN && str_contains($s['message'], 'Hello Ada')));

        // 3. They reply STOP; a new campaign to the same area has nobody left and cannot be sent.
        $this->sms(self::CITIZEN, 'STOP');
        $second = $campaigns->saveDraft($director, ['name' => 'Sample two', 'template_id' => $template->id, 'lga_ids' => [$supporter->lga_id]]);
        $this->assertSame(0, $second->estimated_recipients);
        try {
            $campaigns->submit($second, $director);
            $this->fail('A campaign with no consenting recipients must not be sent.');
        } catch (ValidationException) {
        }
        $this->assertSame(0, MessageCampaign::query()->find($second->id)->messages()->count());

        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }

    public function test_an_agent_reports_by_sms_and_the_result_is_verified_compared_and_packed_as_evidence(): void
    {
        Storage::fake('local');
        $admin = User::factory()->withRole('super_admin')->withTwoFactor()->create();
        $election = app(ElectionSetup::class)->save($admin, ['name' => 'Sample election', 'election_date' => now()->toDateString(), 'checklist' => ['Opened on time']],
            [['code' => 'SPA', 'name' => 'Sample Party A'], ['code' => 'SPB', 'name' => 'Sample Party B']]);
        app(ElectionSetup::class)->activate($election, $admin);
        $ward = $this->ward('udu');
        $unit = new PollingUnit;
        $unit->forceFill(['code' => 'SMP-E2E-1', 'name' => 'Sample unit', 'ward_id' => $ward->id, 'lga_id' => $ward->lga_id, 'senatorial_district_id' => $ward->lga->senatorial_district_id, 'registered_voters' => 400, 'is_sample' => true])->save();

        // 1. A coordinator assigns the agent; the agent reports by SMS without data.
        $coordinator = User::factory()->withRole('lga_coordinator', $ward->lga_id)->create();
        $assignment = app(AgentService::class)->assign($coordinator, Election::query()->find($election->id), $unit->fresh(), 'Sample Agent', '08061230009');
        $this->sms('+2348061230009', 'ARRIVED');
        $this->sms('+2348061230009', 'TURNOUT 150');
        $this->sms('+2348061230009', 'RESULT A=150 V=140 R=4 SPA=90 SPB=50');
        $submission = ResultSubmission::query()->sole();
        $this->assertSame(['no_photo'], array_column($submission->issues, 'code'));

        // 2. Later, with data, the agent adds the photo; a second person verifies.
        app(FieldReports::class)->attachPhoto($submission, $assignment->user, UploadedFile::fake()->image('sheet.jpg'));
        $this->assertSame(ResultStatus::Submitted, $submission->fresh()->status);
        app(ResultReview::class)->verify($submission->fresh(), $coordinator);

        // 3. Official figures are transcribed and compared.
        $official = app(ResultReview::class)->recordOfficial($admin, $election, $unit->fresh(), OfficialSource::Irev, 'https://example.org/irev/sample', ['valid_votes' => 140, 'rejected_votes' => 4, 'total_votes_cast' => 144], ['SPA' => 90, 'SPB' => 50]);
        $this->assertSame('matches', app(ResultReview::class)->compare($submission->fresh(), $official)['status']);
        $summary = app(ElectionMetrics::class)->summary($election, GeoScope::statewideScope());
        $this->assertSame([1, 1, 1], [$summary['arrived'], $summary['verified'], $summary['official']]);

        // 4. The evidence pack carries the original photo with its hash.
        $pack = app(EvidencePackService::class)->request($admin, $election, 'polling_unit', $unit->id)->fresh();
        $this->assertSame(['ready', 1], [$pack->status, $pack->file_count]);

        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }
}
