<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\MessageStatus;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\Drivers\OutboxWhatsAppGateway;
use App\Services\Messaging\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class WhatsAppTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->travelTo(now()->timezone('Africa/Lagos')->setTime(12, 0)->utc());
        config(['services.whatsapp.app_secret' => 'test-app-secret', 'services.whatsapp.verify_token' => 'test-verify']);
    }

    private function signed(array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-app-secret'),
        ], $body);
    }

    public function test_campaigns_send_the_approved_template_with_parameters_in_order(): void
    {
        $director = User::factory()->withRole('state_director')->withTwoFactor()->create();
        $template = app(TemplateService::class)->save(User::factory()->withRole('super_admin')->create(), [
            'name' => 'Sample WA', 'channel' => 'whatsapp', 'purpose' => 'campaign_updates',
            'body_en' => 'Hello {first_name}, sample update for {lga}.', 'provider_template' => 'sample_update_v1',
        ]);
        $this->registerSupporter('08073330001', consents: ['whatsapp' => true], name: 'Ada Sample');
        $this->registerSupporter('08073330002', consents: ['sms' => true], name: 'Sms Only');

        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($director, ['name' => 'WA sample', 'template_id' => $template->id]);
        $campaigns->submit($campaign, $director);

        $sent = OutboxWhatsAppGateway::messages();
        $this->assertCount(1, $sent);
        $this->assertSame('sample_update_v1', $sent[0]['template']);
        $this->assertSame(['Ada', 'Udu'], $sent[0]['parameters']);

        $message = $campaign->messages()->where('status', MessageStatus::Sent)->sole();
        $this->signed(['entry' => [['changes' => [['value' => ['statuses' => [['id' => $message->provider_message_id, 'status' => 'delivered']]]]]]]])->assertOk();
        $this->assertSame(MessageStatus::Delivered, $message->refresh()->status);
    }

    public function test_webhooks_require_a_valid_signature_and_verify_token(): void
    {
        $this->postJson(route('webhooks.whatsapp'), ['entry' => []])->assertForbidden();
        $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'wrong', 'hub_challenge' => 'x']))->assertForbidden();
        $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'test-verify', 'hub_challenge' => 'abc123']))->assertOk()->assertSee('abc123');
    }

    public function test_stop_on_whatsapp_suppresses_every_channel(): void
    {
        $supporter = $this->registerSupporter('08073330003', consents: ['sms' => true, 'whatsapp' => true]);

        $this->signed(['entry' => [['changes' => [['value' => ['messages' => [['from' => '2348073330003', 'type' => 'text', 'text' => ['body' => 'Stop']]]]]]]]])->assertOk();

        $consent = app(ConsentService::class);
        $this->assertFalse($consent->isEligible($supporter->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates));
        $this->assertFalse($consent->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
    }
}
