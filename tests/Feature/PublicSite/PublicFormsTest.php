<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Livewire\PublicSite\ContactForm;
use App\Livewire\PublicSite\EventSignup;
use App\Livewire\PublicSite\SupporterSignup;
use App\Models\ContactMessage;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Events\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class PublicFormsTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
    }

    private function verified(string $e164): void
    {
        session()->put('public_verification.verified', ['phone' => $e164, 'at' => now()->getTimestamp()]);
    }

    public function test_phone_is_verified_by_one_time_code(): void
    {
        $component = Livewire::test(SupporterSignup::class);
        $this->travel(5)->seconds();
        $component->set('phone', '0805 111 2222')->call('sendCode')->assertSet('codeSent', true);

        $code = $this->lastCodeSentTo('+2348051112222');
        $this->assertNotNull($code);
        $component->set('code', $code === '000000' ? '111111' : '000000')->call('confirmCode')->assertHasErrors('code')->assertSet('verifiedPhone', null);
        $component->set('code', $code)->call('confirmCode')->assertSet('verifiedPhone', '+2348051112222');
    }

    public function test_supporter_registers_from_the_website_with_consent_evidence(): void
    {
        $this->verified('+2348051112222');
        $component = Livewire::test(SupporterSignup::class);
        $this->travel(5)->seconds();
        $component->set('fullName', 'Sample Webform Person')->set('lgaId', $this->lga('udu')->id)->set('adult', true)->set('consentSms', true)
            ->call('register')->assertHasNoErrors();

        $supporter = Supporter::query()->where('full_name', 'Sample Webform Person')->firstOrFail();
        $this->assertNotNull($component->get('reference'));
        $this->assertTrue(app(ConsentService::class)->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertFalse(app(ConsentService::class)->isEligible($supporter->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates));
    }

    public function test_registration_requires_a_verified_phone(): void
    {
        $component = Livewire::test(SupporterSignup::class);
        $this->travel(5)->seconds();
        $component->set('fullName', 'Sample Person')->set('lgaId', $this->lga('udu')->id)->set('adult', true)->call('register')->assertHasErrors('phone');
        $this->assertSame(0, Supporter::query()->count());
    }

    public function test_honeypot_and_too_fast_submissions_are_rejected(): void
    {
        $this->verified('+2348051112222');
        Livewire::test(ContactForm::class)->set('name', 'Bot')->set('message', 'Buy cheap things now please')->set('agree', true)->call('send')->assertHasErrors('form');

        $component = Livewire::test(ContactForm::class);
        $this->travel(5)->seconds();
        $component->set('website', 'http://spam.example')->set('name', 'Bot')->set('message', 'Buy cheap things now please')->set('agree', true)->call('send')->assertHasErrors('form');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_messages_are_encrypted_at_rest(): void
    {
        $component = Livewire::test(ContactForm::class);
        $this->travel(5)->seconds();
        $component->set('name', 'Sample Visitor')->set('contact', 'visitor@example.org')->set('message', 'A sample message about a correction.')->set('agree', true)
            ->call('send')->assertHasNoErrors();

        $raw = DB::table('contact_messages')->first();
        $this->assertStringNotContainsString('correction', (string) $raw->message_encrypted);
        $this->assertStringNotContainsString('visitor@example.org', (string) $raw->contact_encrypted);
        $this->assertSame('A sample message about a correction.', ContactMessage::query()->first()->message_encrypted);
    }

    public function test_event_registration_issues_an_opaque_ticket_and_respects_capacity(): void
    {
        $organiser = User::factory()->withRole('state_director')->create();
        $events = app(EventService::class);
        $event = $events->save($organiser, ['title_en' => 'Sample town hall', 'description_en' => 'Sample', 'starts_at' => now()->addWeek(), 'venue' => 'Sample hall', 'lga_id' => $this->lga('udu')->id, 'capacity' => 1]);
        $events->publish($event, $organiser);

        $this->verified('+2348051112222');
        $component = Livewire::test(EventSignup::class, ['event' => $event]);
        $this->travel(5)->seconds();
        $component->set('fullName', 'Sample Attendee')->call('register')->assertHasNoErrors();

        $registration = $event->registrations()->firstOrFail();
        $this->assertSame(40, strlen($registration->ticket_token));
        $this->assertSame(1, $this->messagesTo('+2348051112222'));
        $this->get(route('public.events.ticket', $registration->ticket_token))->assertOk()->assertDontSee('2222')->assertDontSee('Sample Attendee');

        // Registering again returns the same ticket; a second person is refused because the event is full.
        $this->assertFalse($events->register($event, 'Sample Attendee', '08051112222', 'web')['created']);
        $this->expectException(ValidationException::class);
        $events->register($event->fresh(), 'Sample Other', '08051113333', 'web');
    }

    public function test_public_forms_are_rate_limited_per_ip(): void
    {
        $send = function (int $i) {
            $component = Livewire::test(ContactForm::class);
            $this->travel(5)->seconds();

            return $component->set('name', "Sample {$i}")->set('message', 'A sample message for rate limits.')->set('agree', true)->call('send');
        };

        foreach (range(1, 5) as $i) {
            $send($i)->assertHasNoErrors();
        }
        $send(6)->assertHasErrors('form');
        $this->assertSame(5, ContactMessage::query()->count());
    }
}
