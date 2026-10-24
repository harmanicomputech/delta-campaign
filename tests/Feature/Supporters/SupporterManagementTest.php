<?php

declare(strict_types=1);

namespace Tests\Feature\Supporters;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\VerificationStatus;
use App\Livewire\Workspace\Supporters\SupporterShow;
use App\Livewire\Workspace\Supporters\SupportersIndex;
use App\Models\AuditLog;
use App\Models\ContactAttempt;
use App\Models\Supporter;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SuppressionService;
use App\Services\Privacy\ErasureService;
use App\Services\Supporters\DuplicateFinder;
use App\Services\Supporters\StaleRecord;
use App\Services\Supporters\SupporterManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class SupporterManagementTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private SupporterManager $manager;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->manager = app(SupporterManager::class);
        $this->coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
    }

    public function test_corrections_are_versioned_and_audited_without_contact_data(): void
    {
        $supporter = $this->registerSupporter('08031112222');

        $updated = $this->manager->correct($supporter, ['full_name' => 'Sample Person Corrected', 'phone' => '08031113333'], $supporter->version, $this->coordinator, 'Typo in name');

        $this->assertSame(2, $updated->version);
        $this->assertSame('+2348031113333', $updated->phone());
        $entry = AuditLog::query()->where('action', 'supporter.corrected')->firstOrFail();
        $this->assertSame('Sample Person Corrected', $entry->changes['full_name']['to']);
        $this->assertStringNotContainsString('8031113333', json_encode($entry->changes));

        $this->expectException(StaleRecord::class);
        $this->manager->correct($supporter, ['full_name' => 'Lost update'], 1, $this->coordinator, 'stale edit');
    }

    public function test_a_number_cannot_be_moved_onto_another_supporter(): void
    {
        $this->registerSupporter('08031112222');
        $other = $this->registerSupporter('08031114444', name: 'Other');

        $this->expectException(ValidationException::class);
        $this->manager->correct($other, ['phone' => '08031112222'], $other->version, $this->coordinator, 'move number');
    }

    public function test_verification_and_rejection_record_history(): void
    {
        $supporter = $this->registerSupporter('08031112222');

        $this->manager->verify($supporter, $this->coordinator);
        $this->assertSame(VerificationStatus::Verified, $supporter->refresh()->verification_status);

        $this->manager->reject($supporter, $this->coordinator, 'Duplicate person');
        $this->assertSame('Duplicate person', $supporter->refresh()->rejection_reason);
        $this->assertSame(['unverified', 'verified'], WorkflowTransition::query()->orderBy('id')->pluck('from_status')->all());
    }

    public function test_revealing_a_number_is_audited(): void
    {
        $supporter = $this->registerSupporter('08031112222');

        $this->assertSame('+2348031112222', $this->manager->revealPhone($supporter, $this->coordinator));
        $this->assertTrue(AuditLog::query()->where('action', 'supporter.contact_revealed')->where('actor_id', $this->coordinator->id)->exists());
    }

    public function test_follow_ups_encrypt_notes_and_do_not_contact_suppresses_everything(): void
    {
        $supporter = $this->registerSupporter('08031112222');

        $attempt = $this->manager->logContact($supporter, $this->coordinator, ContactChannel::Phone, ContactOutcome::DoNotContact, 'Asked not to be called again', null);

        $this->assertStringNotContainsString('Asked not', (string) DB::table('contact_attempts')->where('id', $attempt->id)->value('notes_encrypted'));
        $this->assertSame('Asked not to be called again', $attempt->refresh()->notes_encrypted);
        $this->assertSame($supporter->ward_id, $attempt->ward_id);
        foreach (ConsentChannel::cases() as $channel) {
            $this->assertTrue(app(SuppressionService::class)->isSuppressed((string) $supporter->phone_hash, $channel));
        }
    }

    public function test_possible_duplicates_are_found_by_similar_name_in_the_same_ward(): void
    {
        $a = $this->registerSupporter('08031112222', name: 'Oghenekaro Sample Edafe');
        $b = $this->registerSupporter('08031115555', name: 'Oghenekaro Sampel Edafe');
        $this->registerSupporter('08031116666', name: 'Completely Different');
        $this->registerSupporter('08031117777', 'sapele', name: 'Oghenekaro Sample Edafe');

        $candidates = app(DuplicateFinder::class)->candidates($a, $this->coordinator);

        $this->assertSame([$b->id], $candidates->pluck('id')->all());
    }

    public function test_merging_moves_history_and_anonymises_the_duplicate(): void
    {
        $primary = $this->registerSupporter('08031112222', consents: []);
        $duplicate = $this->registerSupporter('08031115555', consents: ['whatsapp' => true]);
        $this->manager->logContact($duplicate, $this->coordinator, ContactChannel::Door, ContactOutcome::Reached, null, null);

        $this->manager->merge($primary, $duplicate, $this->coordinator);

        $this->assertNotNull($duplicate->refresh()->erased_at);
        $this->assertSame(1, ContactAttempt::query()->where('supporter_id', $primary->id)->count());
        $this->assertTrue(app(ConsentService::class)->isEligible((string) $primary->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates));
        $this->assertFalse(app(SuppressionService::class)->isSuppressed('x', ConsentChannel::Sms));
    }

    public function test_erasure_anonymises_and_is_enforced_by_the_database(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $this->manager->logContact($supporter, $this->coordinator, ContactChannel::Phone, ContactOutcome::Reached, 'private note', null);
        $hash = $supporter->phone_hash;

        app(ErasureService::class)->anonymise($supporter, $this->coordinator, 'request');
        $supporter->refresh();

        $this->assertSame('[erased]', $supporter->full_name);
        $this->assertNull($supporter->phone_hash);
        $this->assertNull(ContactAttempt::query()->value('notes_encrypted'));
        $this->assertTrue(app(SuppressionService::class)->isSuppressed((string) $hash, ConsentChannel::Voice));
        $this->assertNotNull($supporter->lga_id, 'Anonymous geography remains for counts.');

        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('supporters')->where('id', $supporter->id)->update(['phone_hash' => str_repeat('a', 64)])), QueryException::class);
        $this->assertThrows(fn () => $this->manager->verify($supporter, $this->coordinator), ValidationException::class);
    }

    public function test_legal_hold_blocks_erasure(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $supporter->forceFill(['legal_hold' => true])->save();

        $this->expectException(\RuntimeException::class);
        app(ErasureService::class)->anonymise($supporter, null, 'test');
    }

    public function test_supporter_pages_enforce_geographic_scope(): void
    {
        $inside = $this->registerSupporter('08031112222');
        $outside = $this->registerSupporter('08031119999', 'sapele', name: 'Outside Person');
        $this->signIn($this->coordinator);

        $this->get(route('workspace.supporters.show', $inside))->assertOk()->assertSee($inside->reference)->assertDontSee('+2348031112222');
        $this->get(route('workspace.supporters.show', $outside))->assertNotFound();

        Livewire::test(SupportersIndex::class)
            ->assertSee($inside->full_name)->assertDontSee('Outside Person')
            ->set('search', '08031119999')->assertDontSee('Outside Person')
            ->set('search', '08031112222')->assertSee($inside->full_name);
    }

    public function test_analysts_and_volunteers_cannot_browse_supporters(): void
    {
        $this->signIn(User::factory()->withRole('analyst')->create())->get('/workspace/supporters')->assertForbidden();
        $this->signIn(User::factory()->withRole('volunteer', $this->ward('udu')->id)->create())->get('/workspace/supporters')->assertForbidden();
    }

    public function test_registering_and_bulk_verifying_through_the_page(): void
    {
        $this->signIn($this->coordinator);

        Livewire::test(SupportersIndex::class)
            ->set('fullName', 'Sample Page Person')->set('phone', '0803 000 7777')
            ->set('formLgaId', $this->lga('udu')->id)->set('formWardId', $this->ward('udu')->id)
            ->set('consentSms', true)->set('adult', true)
            ->call('register')->assertHasNoErrors()->assertDispatched('toast');

        $supporter = Supporter::findByPhone('08030007777');
        $this->assertNotNull($supporter);

        Livewire::test(SupportersIndex::class)
            ->set('selected', [$supporter->id])->call('verifySelected');
        $this->assertSame(VerificationStatus::Verified, $supporter->refresh()->verification_status);
    }

    public function test_registration_outside_the_registrars_area_is_refused(): void
    {
        $this->signIn($this->coordinator);

        Livewire::test(SupportersIndex::class)
            ->set('fullName', 'Out Of Area')->set('phone', '0803 000 7778')->set('formLgaId', $this->lga('sapele')->id)->set('adult', true)
            ->call('register')->assertHasErrors('formLgaId');

        $this->assertNull(Supporter::findByPhone('08030007778'));
    }

    public function test_reveal_requires_the_contact_permission(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $this->signIn($this->coordinator);

        Livewire::test(SupporterShow::class, ['supporter' => $supporter])
            ->call('reveal')->assertForbidden();

        $dpo = User::factory()->withTwoFactor()->withRole('data_protection_officer')->create();
        $this->signIn($dpo);
        Livewire::test(SupporterShow::class, ['supporter' => $supporter])
            ->call('reveal')->assertSet('revealedPhone', '+2348031112222');
    }
}
