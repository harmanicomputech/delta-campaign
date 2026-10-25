<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Enums\CaseStatus;
use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Verdict;
use App\Livewire\Workspace\Contact\ContactInbox;
use App\Livewire\Workspace\Events\EventCheckIn;
use App\Livewire\Workspace\Events\EventShow;
use App\Models\CampaignEvent;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Projects\ProjectService;
use App\Services\RapidResponse\RapidResponseService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class OperationsTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    private function event(User $organiser, string $lga = 'udu', array $extra = []): CampaignEvent
    {
        $events = app(EventService::class);
        $event = $events->save($organiser, $extra + ['title_en' => 'Sample rally', 'description_en' => 'Sample', 'starts_at' => now()->addDays(3), 'venue' => 'Sample field', 'lga_id' => $this->lga($lga)->id]);
        $events->publish($event, $organiser);

        return $event->fresh();
    }

    public function test_lga_organisers_create_events_only_in_their_area(): void
    {
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $this->assertSame('udu', $this->event($coordinator)->lga->slug);

        $this->expectException(ValidationException::class);
        $this->event($coordinator, 'warri-south');
    }

    public function test_check_in_needs_a_confirming_action_and_is_idempotent(): void
    {
        $organiser = User::factory()->withRole('state_director')->withTwoFactor()->create();
        $event = $this->event($organiser);
        $registration = app(EventService::class)->register($event, 'Sample Attendee', '08051112222', 'web')['registration'];

        $this->signIn($organiser);
        $component = Livewire::test(EventCheckIn::class, ['token' => $registration->ticket_token])->assertSee('Sample Attendee');
        $this->assertSame(RegistrationStatus::Registered, $registration->fresh()->status);

        $component->call('checkIn')->assertSet('result', 'checked_in');
        $this->assertSame(RegistrationStatus::Attended, $registration->fresh()->status);
        Livewire::test(EventCheckIn::class, ['token' => $registration->ticket_token])->call('checkIn')->assertSet('result', 'already');
    }

    public function test_organisers_from_other_areas_cannot_check_in_tickets(): void
    {
        $event = $this->event(User::factory()->withRole('state_director')->create());
        $registration = app(EventService::class)->register($event, 'Sample Attendee', '08051112222', 'web')['registration'];

        $other = User::factory()->withRole('lga_coordinator', $this->lga('warri-south')->id)->withTwoFactor()->create();
        $this->signIn($other);
        Livewire::test(EventCheckIn::class, ['token' => $registration->ticket_token])->assertHasErrors('token');
        $this->expectException(AuthorizationDenied::class);
        app(EventService::class)->checkIn($registration->ticket_token, $other);
    }

    public function test_cancelling_an_event_notifies_registrants_by_sms(): void
    {
        $organiser = User::factory()->withRole('state_director')->withTwoFactor()->create();
        $event = $this->event($organiser);
        $events = app(EventService::class);
        $events->register($event, 'Sample One', '08051110001', 'web');
        $events->register($event, 'Sample Two', '08051110002', 'web');

        $this->signIn($organiser);
        Livewire::test(EventShow::class, ['event' => $event])->set('reason', 'Sample weather warning')->call('cancel');

        $this->assertSame('cancelled', $event->fresh()->status->value);
        $this->assertSame(2, $this->messagesTo('+2348051110001'));
        $this->get(route('public.events.show', $event->slug))->assertOk()->assertSee(__('events.cancelled'));
    }

    public function test_walk_in_registration_works_after_the_event_starts(): void
    {
        $organiser = User::factory()->withRole('state_director')->withTwoFactor()->create();
        $event = $this->event($organiser);
        $this->travelTo($event->starts_at->addMinutes(30));

        $this->signIn($organiser);
        Livewire::test(EventShow::class, ['event' => $event])->set('attendeeName', 'Sample Walkin')->set('attendeePhone', '08051110009')->call('addAttendee')->assertHasNoErrors();
        $this->assertSame(RegistrationStatus::Attended, $event->registrations()->firstOrFail()->status);
    }

    public function test_project_records_need_a_source_and_a_second_approver(): void
    {
        $editor = User::factory()->withRole('content_editor')->create();
        $approver = User::factory()->withRole('dg')->create();
        $projects = app(ProjectService::class);
        $project = $projects->save($editor, ['title' => 'Sample project', 'description' => 'Sample', 'category' => 'water', 'source_note' => 'Sample source', 'lga_id' => $this->lga('udu')->id]);

        $this->get(route('public.projects.show', $project->slug))->assertNotFound();
        $projects->submit($project, $editor);
        $projects->publish($project, $approver);
        $this->get(route('public.projects.show', $project->slug))->assertOk()->assertSee('Sample source');

        // Editing takes it off the public site until approved again.
        $projects->save($editor, ['title' => 'Sample project', 'description' => 'Changed', 'category' => 'water', 'source_note' => 'Sample source'], $project->fresh());
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
        $this->get(route('public.projects.show', $project->slug))->assertNotFound();
    }

    public function test_rapid_response_approval_creates_a_draft_fact_check_not_a_publication(): void
    {
        Storage::fake('local');
        $officer = User::factory()->withRole('rapid_response')->create();
        $approver = User::factory()->withRole('super_admin')->create();
        $rapid = app(RapidResponseService::class);

        $case = $rapid->open($officer, ['title' => 'Sample claim', 'claim' => 'Sample claim text', 'source_urls' => ['https://example.org/post', 'javascript:alert(1)'], 'requires_legal' => true]);
        $this->assertSame(['https://example.org/post'], $case->source_urls);

        $rapid->startInvestigation($case, $officer);
        $note = $rapid->addEvidence($case, $officer, UploadedFile::fake()->createWithContent('evidence.txt', 'sample evidence'));
        $this->assertSame(hash('sha256', 'sample evidence'), $note->sha256);

        try {
            $rapid->requestApproval($case, $officer);
            $this->fail('A finding is required before approval.');
        } catch (ValidationException) {
        }
        $rapid->recordFinding($case, $officer, Verdict::Misleading, 'Sample finding text');
        $rapid->requestApproval($case, $officer);
        $this->assertSame(CaseStatus::LegalReview, $case->fresh()->status);

        $rapid->clearLegal($case->fresh(), $approver, 'Sample legal note');
        $rapid->approve($case->fresh(), $approver);

        $case->refresh();
        $this->assertSame(CaseStatus::Approved, $case->status);
        $this->assertSame(ContentStatus::Draft, $case->content->status);
        $this->assertFalse($case->content->isLive());
    }

    public function test_rapid_response_cases_cannot_be_approved_by_their_creator(): void
    {
        $admin = User::factory()->withRole('super_admin')->create();
        $rapid = app(RapidResponseService::class);
        $case = $rapid->open($admin, ['title' => 'Sample', 'claim' => 'Sample claim']);
        $rapid->startInvestigation($case, $admin);
        $rapid->recordFinding($case, $admin, Verdict::False, 'Sample finding');
        $rapid->requestApproval($case, $admin);

        $this->expectException(AuthorizationDenied::class);
        $rapid->approve($case->fresh(), $admin);
    }

    public function test_evidence_downloads_require_permission_and_are_audited(): void
    {
        Storage::fake('local');
        $officer = User::factory()->withRole('rapid_response')->withTwoFactor()->create();
        $case = app(RapidResponseService::class)->open($officer, ['title' => 'Sample', 'claim' => 'Sample claim']);
        $note = app(RapidResponseService::class)->addEvidence($case, $officer, UploadedFile::fake()->createWithContent('e.txt', 'x'));

        $this->signIn($officer)->get(route('workspace.rapid-response.evidence', [$case, $note->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'rapid_response.evidence_downloaded']);

        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $this->signIn($volunteer)->get(route('workspace.rapid-response.evidence', [$case, $note->id]))->assertForbidden();
    }

    public function test_contact_inbox_audits_each_opened_message(): void
    {
        $editor = User::factory()->withRole('content_editor')->create();
        $message = new ContactMessage(['name' => 'Sample Visitor', 'topic' => 'general']);
        $message->message_encrypted = 'Sample message body';
        $message->save();

        $this->signIn($editor);
        Livewire::test(ContactInbox::class)->call('openMessage', $message->id)->assertSee('Sample message body')->call('setStatus', 'handled');
        $this->assertSame('handled', $message->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact.viewed']);

        $this->signIn(User::factory()->withRole('analyst')->create())->get(route('workspace.contact.index'))->assertForbidden();
    }
}
