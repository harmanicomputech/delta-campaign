<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOperations;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ContactOutcome;
use App\Enums\QualityFlag;
use App\Enums\WalkListStatus;
use App\Livewire\Workspace\Canvassing\WalkListShow;
use App\Livewire\Workspace\Canvassing\WalkListsIndex;
use App\Models\ContactAttempt;
use App\Models\User;
use App\Models\WalkList;
use App\Services\Canvassing\CanvassingService;
use App\Services\Consent\ConsentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class CanvassingTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private CanvassingService $canvassing;

    private User $coordinator;

    private User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->canvassing = app(CanvassingService::class);
        $this->coordinator = User::factory()->withRole('ward_coordinator', $this->ward('udu')->id)->create();
        $this->volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
    }

    private function list(bool $supporters = false): WalkList
    {
        $list = $this->canvassing->createWalkList($this->coordinator, 'Sample round', $this->ward('udu'), ['Stop A', '', 'Stop B'], $supporters);
        $this->canvassing->assign($list, $this->volunteer, $this->coordinator);

        return $list->refresh();
    }

    public function test_walk_lists_are_limited_to_the_creators_area(): void
    {
        $this->expectException(AuthorizationDenied::class);
        $this->canvassing->createWalkList($this->coordinator, 'Elsewhere', $this->ward('sapele'), ['Stop']);
    }

    public function test_empty_lists_are_rejected_and_supporters_can_be_included(): void
    {
        $this->registerSupporter('08031110001');
        $this->registerSupporter('08031110002', 'sapele');

        try {
            $this->canvassing->createWalkList($this->coordinator, 'Empty', $this->ward('udu'), ['  ']);
            $this->fail('Empty list accepted');
        } catch (ValidationException) {
        }

        $list = $this->list(supporters: true);
        $this->assertSame(3, $list->entries()->count());
        $this->assertSame(1, $list->entries()->whereNotNull('supporter_id')->count());
        $this->assertSame(1, $this->volunteer->notifications()->count());
    }

    public function test_assignment_requires_canvassing_access_in_the_ward(): void
    {
        $list = $this->canvassing->createWalkList($this->coordinator, 'Sample', $this->ward('udu'), ['Stop']);
        $outsider = User::factory()->withRole('volunteer', $this->ward('udu', 'W02')->id)->create();

        $this->expectException(ValidationException::class);
        $this->canvassing->assign($list, $outsider, $this->coordinator);
    }

    public function test_contact_capture_is_idempotent_on_the_client_uuid(): void
    {
        $list = $this->list();
        $entry = $list->entries()->orderBy('position')->first();
        $uuid = (string) Str::uuid();

        $first = $this->canvassing->recordContact($entry, $this->volunteer, ContactOutcome::Reached, uuid: $uuid);
        $retry = $this->canvassing->recordContact($entry, $this->volunteer, ContactOutcome::Reached, uuid: $uuid);

        $this->assertTrue($first->is($retry));
        $this->assertSame(1, ContactAttempt::query()->count());
        $this->assertSame(WalkListStatus::InProgress, $list->refresh()->status);
        $this->assertSame($list->ward_id, $first->ward_id);
        $this->assertNotNull($first->senatorial_district_id);
    }

    public function test_repeat_contacts_within_an_hour_are_flagged_for_review(): void
    {
        $supporter = $this->registerSupporter('08031110003');
        $list = $this->list(supporters: true);
        $entry = $list->entries()->whereNotNull('supporter_id')->first();

        $this->assertSame(QualityFlag::Ok, $this->canvassing->recordContact($entry, $this->volunteer, ContactOutcome::NotHome)->quality_flag);
        $second = $this->canvassing->recordContact($entry, $this->volunteer, ContactOutcome::Reached);
        $this->assertSame(QualityFlag::SuspectDuplicate, $second->quality_flag);
        $this->assertSame($supporter->id, $second->supporter_id);

        Livewire::actingAs($this->coordinator)->test(WalkListsIndex::class, ['view' => 'review'])
            ->assertSee($supporter->reference)
            ->call('review', $second->id, 'ok');
        $this->assertSame(QualityFlag::Ok, $second->refresh()->quality_flag);
        $this->assertSame($this->coordinator->id, $second->reviewed_by);
    }

    public function test_do_not_contact_withdraws_consent_immediately(): void
    {
        $supporter = $this->registerSupporter('08031110004');
        $list = $this->list(supporters: true);
        $consent = app(ConsentService::class);
        $this->assertTrue($consent->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));

        $this->canvassing->recordContact($list->entries()->whereNotNull('supporter_id')->first(), $this->volunteer, ContactOutcome::DoNotContact);

        $this->assertFalse($consent->isEligible($supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
    }

    public function test_future_device_times_are_clamped_and_offline_flag_kept(): void
    {
        $list = $this->list();
        $attempt = $this->canvassing->recordContact($list->entries()->first(), $this->volunteer, ContactOutcome::Reached, occurredAt: now()->addDay(), offline: true);

        $this->assertTrue($attempt->occurred_at->lte(now()));
        $this->assertTrue($attempt->recorded_offline);
    }

    public function test_only_the_assignee_or_area_managers_can_record(): void
    {
        $list = $this->list();
        $other = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $this->expectException(AuthorizationDenied::class);
        $this->canvassing->recordContact($list->entries()->first(), $other, ContactOutcome::Reached);
    }

    public function test_finishing_every_stop_completes_the_list(): void
    {
        $list = $this->list();
        foreach ($list->entries as $entry) {
            $this->canvassing->recordContact($entry, $this->volunteer, ContactOutcome::NotHome);
        }

        $this->assertSame(WalkListStatus::Completed, $list->refresh()->status);
        $this->assertSame(['done' => 2, 'total' => 2], $list->progress());
    }

    public function test_quick_capture_in_the_ui_and_scope_isolation(): void
    {
        $list = $this->list();
        $entry = $list->entries()->first();
        $uuid = (string) Str::uuid();

        Livewire::actingAs($this->volunteer)->test(WalkListShow::class, ['list' => $list])
            ->set("notes.{$entry->id}", 'Asked us to come back on Saturday')
            ->call('record', $entry->id, 'follow_up_requested', $uuid)
            ->call('record', $entry->id, 'follow_up_requested', $uuid)
            ->assertHasNoErrors();

        $attempt = ContactAttempt::query()->sole();
        $this->assertSame('Asked us to come back on Saturday', $attempt->notes_encrypted);
        $this->assertStringNotContainsString('Saturday', (string) $attempt->getRawOriginal('notes_encrypted'));

        Livewire::actingAs($this->volunteer)->test(WalkListShow::class, ['list' => $list])
            ->call('record', $entry->id, 'voted_for_us', (string) Str::uuid())
            ->assertHasErrors('outcome');

        $stranger = User::factory()->withRole('ward_coordinator', $this->ward('sapele')->id)->create();
        $this->signIn($stranger)->get(route('workspace.canvassing.show', $list))->assertNotFound();
        $this->signIn($this->volunteer)->get(route('workspace.canvassing.show', $list))->assertOk()->assertSee('Stop A');
        $this->signIn($this->volunteer)->get(route('workspace.canvassing.index'))->assertOk()->assertSee('Sample round');
    }
}
