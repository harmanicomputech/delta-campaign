<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CampaignStatus;
use App\Enums\ConsentChannel;
use App\Enums\MessageStatus;
use App\Enums\RequestChannel;
use App\Enums\SkipReason;
use App\Jobs\SendOutboundMessage;
use App\Livewire\Workspace\Approvals\ApprovalsInbox;
use App\Livewire\Workspace\Messaging\CampaignEditor;
use App\Livewire\Workspace\Messaging\CampaignShow;
use App\Models\MessageCampaign;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\CampaignAwaitingApproval;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageDispatcher;
use App\Services\Messaging\MessageRenderer;
use App\Services\Messaging\ProviderHealth;
use App\Services\Messaging\TemplateService;
use App\Services\Settings\Settings;
use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use App\Services\Sms\SmsResult;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\Doubles\ScriptedSmsGateway;
use Tests\TestCase;

final class MessagingTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private User $director;

    private User $dg;

    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->travelTo(now()->timezone('Africa/Lagos')->setTime(12, 0)->utc()); // Outside quiet hours.
        $this->director = User::factory()->withRole('state_director')->withTwoFactor()->create(['name' => 'Sample Director']);
        $this->dg = User::factory()->withRole('dg')->withTwoFactor()->create(['name' => 'Sample DG']);
        $this->template = app(TemplateService::class)->save(User::factory()->withRole('super_admin')->create(), [
            'name' => 'Sample update', 'channel' => 'sms', 'purpose' => 'campaign_updates',
            'body_en' => 'Hello {first_name} from {lga}! Sample update. Reply STOP to opt out.', 'body_pcm' => 'How far {first_name}! Sample update. Reply STOP to stop am.',
        ]);
    }

    /** @return array<string, mixed> */
    private function draftData(array $overrides = []): array
    {
        return $overrides + ['name' => 'Sample campaign', 'template_id' => $this->template->id, 'lga_ids' => [$this->lga('udu')->id]];
    }

    private function supporters(int $consenting, int $without = 0): void
    {
        foreach (range(1, $consenting) as $i) {
            $this->registerSupporter(sprintf('0807100%04d', $i), name: "Sample Person{$i} Example");
        }
        foreach ($without > 0 ? range(1, $without) : [] as $i) {
            $this->registerSupporter(sprintf('0807200%04d', $i), consents: [], name: "Sample Nocon{$i} Example");
        }
    }

    public function test_templates_must_explain_opt_out_and_use_known_placeholders(): void
    {
        $admin = User::factory()->withRole('super_admin')->create();
        $service = app(TemplateService::class);

        foreach ([
            ['body_en' => 'No opt-out text here'],
            ['body_en' => 'Hi {nickname}. Reply STOP to opt out'],
            ['channel' => 'whatsapp', 'body_en' => 'Hello {first_name}'],
        ] as $overrides) {
            try {
                $service->save($admin, $overrides + ['name' => 'Bad', 'channel' => 'sms', 'purpose' => 'campaign_updates', 'body_en' => '']);
                $this->fail('Invalid template accepted: '.json_encode($overrides));
            } catch (ValidationException) {
            }
        }

        $this->assertSame(1, MessageTemplate::query()->count());
    }

    public function test_segments_are_counted_for_gsm_and_unicode(): void
    {
        $renderer = app(MessageRenderer::class);

        $this->assertSame(1, $renderer->segments(str_repeat('a', 160)));
        $this->assertSame(2, $renderer->segments(str_repeat('a', 161)));
        $this->assertSame(1, $renderer->segments(str_repeat('ọ', 70)));
        $this->assertSame(2, $renderer->segments(str_repeat('ọ', 71)));
        $this->assertSame(2, $renderer->segments(str_repeat('€', 81)), 'Extended characters count double');
    }

    public function test_small_sends_by_an_approver_go_out_and_only_to_consenting_people(): void
    {
        $this->supporters(3, without: 2);
        $campaigns = app(CampaignService::class);

        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $this->assertSame(3, $campaign->estimated_recipients);
        $this->assertSame(1, $campaign->segments);
        $this->assertSame('12.00', (string) $campaign->estimated_cost);

        $campaigns->submit($campaign, $this->director);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);
        $this->assertSame($this->director->id, $campaign->approved_by);
        $this->assertSame(3, $campaign->messages()->where('status', MessageStatus::Sent)->count());
        $this->assertSame(2, $campaign->messages()->where('skip_reason', SkipReason::NoConsent)->count());

        $sms = collect(OutboxSmsGateway::messages())->firstWhere('to', '+2348071000001');
        $this->assertSame('Hello Sample from Udu! Sample update. Reply STOP to opt out.', $sms['message']);
    }

    public function test_large_sends_need_an_independent_approver_covering_the_area(): void
    {
        app(Settings::class)->set('privacy.retention_months', 36, $this->dg); // warm settings cache
        app(Settings::class)->set('messaging.approval_threshold', 2, $this->dg);
        $this->supporters(3);
        $campaigns = app(CampaignService::class);
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $campaign = $campaigns->saveDraft($coordinator, $this->draftData());
        $campaigns->submit($campaign, $coordinator);
        $this->assertSame(CampaignStatus::PendingApproval, $campaign->refresh()->status);
        $this->assertSame(1, $this->director->notifications()->where('type', CampaignAwaitingApproval::class)->count());

        try {
            $campaigns->approve($campaign, $coordinator);
            $this->fail('Creator without approval rights approved');
        } catch (AuthorizationDenied) {
        }

        $own = $campaigns->saveDraft($this->director, $this->draftData(['name' => 'Own large']));
        $campaigns->submit($own, $this->director);
        try {
            $campaigns->approve($own->refresh(), $this->director);
            $this->fail('Approved own large campaign');
        } catch (AuthorizationDenied) {
        }

        $campaigns->approve($campaign, $this->dg);
        $campaign->refresh();
        $this->assertSame($this->dg->id, $campaign->approved_by);
        $this->assertSame(3, $campaign->messages()->where('status', MessageStatus::Sent)->count());
    }

    public function test_audiences_stay_inside_the_creators_area(): void
    {
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $this->expectException(ValidationException::class);
        app(CampaignService::class)->saveDraft($coordinator, $this->draftData(['lga_ids' => [$this->lga('sapele')->id]]));
    }

    public function test_opt_outs_after_queueing_are_honoured_at_send_time(): void
    {
        Queue::fake([SendOutboundMessage::class]);
        $this->supporters(2);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $campaigns->submit($campaign, $this->director);
        $this->assertSame(2, $campaign->messages()->where('status', MessageStatus::Queued)->count());

        $optedOut = $campaign->messages()->orderBy('id')->first();
        app(ConsentService::class)->withdrawAll($optedOut->phone_hash, null, RequestChannel::Sms);

        $dispatcher = app(MessageDispatcher::class);
        foreach ($campaign->messages as $message) {
            $this->assertNull($dispatcher->dispatch($message));
        }

        $this->assertSame(SkipReason::Suppressed, $optedOut->refresh()->skip_reason);
        $this->assertSame(1, $campaign->messages()->where('status', MessageStatus::Sent)->count());
        $this->assertSame(CampaignStatus::Completed, $campaign->refresh()->status);
    }

    public function test_quiet_hours_and_budget_hold_messages_without_losing_them(): void
    {
        Queue::fake([SendOutboundMessage::class]);
        $this->supporters(1);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $campaigns->submit($campaign, $this->director);
        $message = $campaign->messages()->sole();
        $dispatcher = app(MessageDispatcher::class);

        $this->travelTo(now()->timezone('Africa/Lagos')->setTime(22, 30)->utc());
        $wait = $dispatcher->dispatch($message);
        $this->assertSame(9.5 * 3600, (float) $wait);
        $this->assertSame(MessageStatus::Queued, $message->refresh()->status);

        $this->travelTo(now()->timezone('Africa/Lagos')->setTime(10, 0)->addDay()->utc());
        app(Settings::class)->set('messaging.monthly_budget', 1, $this->dg);
        $this->assertSame(300, $dispatcher->dispatch($message));
        $this->assertSame(CampaignStatus::Paused, $campaign->refresh()->status);

        app(Settings::class)->set('messaging.monthly_budget', 1000, $this->dg);
        $campaigns->resume($campaign, $this->director);
        $this->assertNull($dispatcher->dispatch($message));
        $this->assertSame(MessageStatus::Sent, $message->refresh()->status);
    }

    public function test_permanent_failures_stop_and_temporary_ones_retry_a_limited_number_of_times(): void
    {
        Queue::fake([SendOutboundMessage::class]);
        $this->supporters(2);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $campaigns->submit($campaign, $this->director);
        [$first, $second] = $campaign->messages()->orderBy('id')->get()->all();

        $this->app->instance(SmsGateway::class, new ScriptedSmsGateway([SmsResult::failed('InvalidPhoneNumber', permanent: true)]));
        $this->assertNull(app(MessageDispatcher::class)->dispatch($first));
        $this->assertSame(MessageStatus::Failed, $first->refresh()->status);
        $this->assertSame(1, $first->attempts);

        $this->app->instance(SmsGateway::class, new ScriptedSmsGateway([SmsResult::failed('ConnectionError', permanent: false)]));
        $dispatcher = app(MessageDispatcher::class);
        $this->assertSame(60, $dispatcher->dispatch($second));
        $this->assertSame(300, $dispatcher->dispatch($second));
        $this->assertNull($dispatcher->dispatch($second));
        $this->assertSame(MessageStatus::Failed, $second->refresh()->status);
        $this->assertSame(3, $second->attempts);
    }

    public function test_a_failing_provider_trips_and_sending_waits(): void
    {
        $health = app(ProviderHealth::class);
        foreach (range(1, ProviderHealth::TRIP_AFTER) as $i) {
            $health->failure(ConsentChannel::Sms, 'HttpError500');
        }

        $this->assertGreaterThan(0, $health->trippedFor(ConsentChannel::Sms));
        $this->assertSame('down', $health->summary(ConsentChannel::Sms)['status']);
        $this->assertSame(0, $health->trippedFor(ConsentChannel::Whatsapp));
    }

    public function test_delivery_receipts_update_messages(): void
    {
        $this->supporters(1);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $campaigns->submit($campaign, $this->director);
        $message = $campaign->messages()->sole();

        $this->post(route('webhooks.sms.delivery'), ['id' => $message->provider_message_id, 'status' => 'Success'])->assertForbidden();
        $this->post(route('webhooks.sms.delivery', ['key' => 'test-webhook-secret']), ['id' => $message->provider_message_id, 'status' => 'Success'])->assertOk();

        $this->assertSame(MessageStatus::Delivered, $message->refresh()->status);
    }

    public function test_cancelling_stops_queued_messages(): void
    {
        Queue::fake([SendOutboundMessage::class]);
        $this->supporters(2);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->saveDraft($this->director, $this->draftData());
        $campaigns->submit($campaign, $this->director);

        $campaigns->cancel($campaign->refresh(), $this->director);

        $this->assertSame(2, $campaign->messages()->where('status', MessageStatus::Cancelled)->count());
        $this->assertNull(app(MessageDispatcher::class)->dispatch($campaign->messages()->first()));
    }

    public function test_the_screens_respect_roles_and_scope(): void
    {
        app(Settings::class)->set('messaging.approval_threshold', 1, $this->dg);
        $this->supporters(1);
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        Livewire::actingAs($coordinator)->test(CampaignEditor::class)
            ->set('name', 'Sample screen campaign')->set('templateId', $this->template->id)
            ->set('lgaIds', [(string) $this->lga('udu')->id])
            ->assertSee(__('messaging.eligible', ['channel' => 'SMS']))
            ->call('submit')->assertHasNoErrors();

        $campaign = MessageCampaign::query()->sole();
        $this->assertSame(CampaignStatus::PendingApproval, $campaign->status);

        Livewire::actingAs($this->dg)->test(ApprovalsInbox::class)->assertSee('Sample screen campaign');
        Livewire::actingAs($this->dg)->test(CampaignShow::class, ['campaign' => $campaign])->call('approve')->assertDispatched('toast', type: 'success');
        $this->assertSame(CampaignStatus::Completed, $campaign->refresh()->status);

        $stranger = User::factory()->withRole('lga_coordinator', $this->lga('sapele')->id)->create();
        $this->signIn($stranger)->get(route('workspace.messaging.show', $campaign))->assertNotFound();
        $this->signIn($coordinator)->get(route('workspace.messaging.show', $campaign))->assertOk()->assertSee('Sample DG');
        $this->signIn($coordinator)->get(route('workspace.messaging.index'))->assertOk()->assertSee('Sample screen campaign');
        $this->signIn(User::factory()->withRole('volunteer', $this->ward('udu')->id)->create())->get(route('workspace.messaging.index'))->assertForbidden();
    }
}
