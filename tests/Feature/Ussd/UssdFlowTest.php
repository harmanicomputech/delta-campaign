<?php

declare(strict_types=1);

namespace Tests\Feature\Ussd;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\DsrType;
use App\Models\AuditLog;
use App\Models\CallbackRequest;
use App\Models\DataSubjectRequest;
use App\Models\PollingUnit;
use App\Models\Supporter;
use App\Models\Task;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\Callbacks\CallbackService;
use App\Services\Consent\ConsentService;
use App\Services\Users\AuthorizationDenied;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class UssdFlowTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private const string PHONE = '+2348051234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
    }

    /** One provider callback. $text is cumulative, as Africa's Talking sends it. */
    private function ussd(string $text, string $session = 'ATUid_1', string $phone = self::PHONE): TestResponse
    {
        return $this->post(route('webhooks.ussd', ['key' => 'test-webhook-secret']), ['sessionId' => $session, 'serviceCode' => '*384*1#', 'phoneNumber' => $phone, 'text' => $text]);
    }

    /** Dials and walks through $steps, returning the last response body. */
    private function walk(array $steps, string $session = 'ATUid_1', string $phone = self::PHONE): string
    {
        $this->ussd('', $session, $phone);
        $text = '';
        $body = '';
        foreach ($steps as $step) {
            $text = $text === '' ? (string) $step : $text.'*'.$step;
            $body = (string) $this->ussd($text, $session, $phone)->assertOk()->getContent();
        }

        return $body;
    }

    private function lgaOption(string $name, string $body): string
    {
        $this->assertMatchesRegularExpression('/(\d+)\. '.preg_quote($name, '/').'/', $body);
        preg_match('/(\d+)\. '.preg_quote($name, '/').'/', $body, $m);

        return $m[1];
    }

    public function test_the_session_opens_with_the_language_menu_in_the_provider_format(): void
    {
        $this->ussd('')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('CON Welcome to', false)->assertSee('1. English')->assertSee('2. Pidgin');
        $this->assertStringContainsString('6. Messages & my data', (string) $this->ussd('1')->getContent());
    }

    public function test_joining_registers_a_phone_verified_supporter_with_sms_consent(): void
    {
        $this->ussd('');
        $this->ussd('1');
        $this->ussd('1*1');
        $lgas = (string) $this->ussd('1*1*Ada Sample')->getContent();
        $option = $this->lgaOption('Aniocha North', $lgas);
        $this->assertStringContainsString('SMS', (string) $this->ussd("1*1*Ada Sample*{$option}")->getContent());
        $this->ussd("1*1*Ada Sample*{$option}*1");
        $end = (string) $this->ussd("1*1*Ada Sample*{$option}*1*1")->getContent();

        $supporter = Supporter::query()->sole();
        $this->assertStringStartsWith('END Thank you, Ada Sample!', $end);
        $this->assertStringContainsString($supporter->reference, $end);
        $this->assertSame('ussd', $supporter->source->value);
        $this->assertNotNull($supporter->phone_verified_at);
        $this->assertSame('Aniocha North', $supporter->lga->name);
        $this->assertTrue(app(ConsentService::class)->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));

        // Dialling again: already registered.
        $again = $this->walk(['1', '1', 'Ada Sample', $option, '2', '1'], 'ATUid_2');
        $this->assertStringContainsString('already registered', $again);
        $this->assertSame(1, Supporter::query()->count());
    }

    public function test_provider_retries_of_the_same_step_are_answered_identically_and_not_repeated(): void
    {
        $this->ussd('');
        $first = (string) $this->ussd('1')->getContent();
        $retry = (string) $this->ussd('1')->getContent();

        $this->assertSame($first, $retry);
        $this->assertStringContainsString('Main menu', $retry);
    }

    public function test_under_18s_are_not_registered(): void
    {
        $body = $this->walk(['1', '1', 'Ada Sample', '1', '1', '2']);

        $this->assertStringStartsWith('END Sorry, you must be 18', $body);
        $this->assertSame(0, Supporter::query()->count());
    }

    public function test_lists_paginate_and_navigation_goes_back_and_home(): void
    {
        $first = $this->walk(['1', '1', 'Ada Sample']);
        $this->assertStringContainsString('98. More', $first);
        $this->assertStringContainsString('0. Back', $first);

        $second = (string) $this->ussd('1*1*Ada Sample*98')->getContent();
        $this->assertNotSame($first, $second);
        $this->assertStringContainsString('1. ', $second);

        $this->assertSame($first, (string) $this->ussd('1*1*Ada Sample*98*0')->getContent(), '0 returns to the previous page');
        $this->assertStringContainsString('Enter your full name', (string) $this->ussd('1*1*Ada Sample*98*0*0')->getContent());
        $this->assertStringContainsString('Main menu', (string) $this->ussd('1*1*Ada Sample*98*0*0*00')->getContent());
    }

    public function test_invalid_input_is_explained_and_the_question_repeated(): void
    {
        $body = $this->walk(['1', '7']);
        $this->assertStringStartsWith('CON Invalid choice.', $body);
        $this->assertStringContainsString('1. Join as a supporter', $body);

        $this->ussd('1*7*1');
        $this->assertStringContainsString('Enter a valid name.', (string) $this->ussd('1*7*1*12345')->getContent());
    }

    public function test_volunteer_registration_records_the_ward(): void
    {
        $ward = $this->ward('udu');
        $lgas = $this->walk(['1', '2', 'Sample Helper']);
        $page = str_contains($lgas, 'Udu') ? [] : ['98'];
        $body = $this->walk(['1', '2', 'Sample Helper', ...$page], 'ATUid_v');
        $option = $this->lgaOption('Udu', $body);
        $wards = (string) $this->ussd(implode('*', ['1', '2', 'Sample Helper', ...$page, $option]), 'ATUid_v')->getContent();
        $this->assertStringContainsString($ward->name, $wards);
        $end = (string) $this->ussd(implode('*', ['1', '2', 'Sample Helper', ...$page, $option, '1']), 'ATUid_v')->getContent();

        $this->assertStringStartsWith('END Thank you! Your application is received.', $end);
        $volunteer = Volunteer::query()->sole();
        $this->assertSame($ward->id, $volunteer->ward_id);
        $this->assertSame('ussd', $volunteer->source);
    }

    public function test_polling_units_are_found_by_code_or_browsing(): void
    {
        $ward = $this->ward('udu');
        PollingUnit::query()->create(['ward_id' => $ward->id, 'code' => 'SMP-UDU-001', 'name' => 'Sample Primary School', 'is_sample' => true]);

        $this->assertStringContainsString('Code not found', $this->walk(['1', '3', '1', 'NOPE-1']));
        $found = $this->walk(['1', '3', '1', 'smp-udu-001'], 'ATUid_pu');
        $this->assertStringStartsWith('END Sample Primary School', $found);
        $this->assertStringContainsString('LGA: Udu', $found);
        $this->assertStringContainsString('(SAMPLE data)', $found);
    }

    public function test_projects_screen_never_invents_projects(): void
    {
        $this->assertStringStartsWith('END No approved projects are published for Aniocha North yet.', $this->walk(['1', '4', '1']));
    }

    public function test_callback_requests_become_tasks_once_per_day_and_numbers_are_revealed_with_an_audit(): void
    {
        $lgas = $this->walk(['1', '5', '3']);
        $page = str_contains($lgas, '. Udu') ? [] : ['98'];
        $body = $page ? (string) $this->ussd('1*5*3*98')->getContent() : $lgas;
        $option = $this->lgaOption('Udu', $body);
        $end = (string) $this->ussd(implode('*', ['1', '5', '3', ...$page, $option]))->getContent();

        $request = CallbackRequest::query()->sole();
        $this->assertStringContainsString($request->reference, $end);
        $task = Task::query()->sole();
        $this->assertSame('high', $task->priority->value);
        $this->assertSame($this->lga('udu')->id, $task->lga_id);
        $this->assertStringNotContainsString('8051234567', $task->title.$task->description);

        $again = $this->walk(['1', '5', '1', ...$page, $option], 'ATUid_cb2');
        $this->assertStringContainsString('already have your request', $again);
        $this->assertSame(1, CallbackRequest::query()->count());

        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $this->assertSame(self::PHONE, app(CallbackService::class)->reveal($request, $coordinator));
        $this->assertTrue(AuditLog::query()->where('action', 'callback.number_revealed')->exists());

        $stranger = User::factory()->withRole('lga_coordinator', $this->lga('sapele')->id)->create();
        $this->expectException(AuthorizationDenied::class);
        app(CallbackService::class)->reveal($request, $stranger);
    }

    public function test_preferences_stop_resume_and_request_deletion(): void
    {
        $hash = PhoneHasher::hash(self::PHONE);
        $consent = app(ConsentService::class);

        $this->assertStringContainsString('will not receive', $this->walk(['1', '6', '1', '1'], 'ATUid_p1'));
        $this->assertFalse($consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));

        $this->assertStringContainsString('You will get SMS updates', $this->walk(['1', '6', '2', '1'], 'ATUid_p2'));
        $this->assertTrue($consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));

        $this->assertStringStartsWith('END Cancelled.', $this->walk(['1', '6', '3', '2'], 'ATUid_p3'));
        $end = $this->walk(['1', '6', '3', '1'], 'ATUid_p4');
        $request = DataSubjectRequest::query()->sole();
        $this->assertSame(DsrType::Erasure, $request->type);
        $this->assertNotNull($request->identity_verified_at);
        $this->assertStringContainsString($request->reference, $end);
    }

    public function test_pidgin_is_used_throughout_the_session(): void
    {
        $this->assertStringContainsString('Make dem call me', $this->walk(['2']));
    }

    public function test_expired_sessions_and_bad_requests_end_politely(): void
    {
        $this->ussd('1*1', 'ATUid_lost')->assertSee('END Your session expired', false);
        $this->ussd('', 'ATUid_x', '+14155550123')->assertSee('END This service is available to Nigerian', false);
        $this->post(route('webhooks.ussd', ['key' => 'test-webhook-secret']), [])->assertSee('END Sorry', false);
    }

    public function test_webhooks_require_the_shared_secret(): void
    {
        $this->post(route('webhooks.ussd'), ['sessionId' => 'a', 'phoneNumber' => self::PHONE, 'text' => ''])->assertForbidden();
        $this->post(route('webhooks.ussd', ['key' => 'wrong']), ['sessionId' => 'a', 'phoneNumber' => self::PHONE, 'text' => ''])->assertForbidden();

        config(['services.africastalking.webhook_secret' => '']);
        $this->post(route('webhooks.ussd', ['key' => '']), ['sessionId' => 'a', 'phoneNumber' => self::PHONE, 'text' => ''])->assertForbidden();
    }
}
