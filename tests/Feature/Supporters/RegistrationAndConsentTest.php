<?php

declare(strict_types=1);

namespace Tests\Feature\Supporters;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use App\Enums\SupporterSource;
use App\Enums\SuppressionReason;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\PollingUnit;
use App\Models\PrivacyNotice;
use App\Models\Supporter;
use App\Models\Suppression;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Consent\SuppressionService;
use App\Services\Privacy\ErasureService;
use App\Services\Supporters\SupporterRegistrar;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class RegistrationAndConsentTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private ConsentService $consent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
        $this->consent = app(ConsentService::class);
    }

    public function test_registration_stores_minimal_encrypted_data_and_explicit_consent(): void
    {
        $supporter = $this->registerSupporter('0803 111 2222', consents: ['sms' => true, 'whatsapp' => false]);
        $row = DB::table('supporters')->where('id', $supporter->id)->first();

        $this->assertStringStartsWith('SUP-', $supporter->reference);
        $this->assertStringNotContainsString('8031112222', (string) $row->phone_encrypted);
        $this->assertSame(PhoneHasher::hash('+2348031112222'), $row->phone_hash);
        $this->assertNotNull($supporter->adult_confirmed_at);
        $this->assertSame($this->ward('udu')->lga_id, $supporter->lga_id);
        $this->assertNotNull($supporter->senatorial_district_id);

        $this->assertSame(ConsentStatus::Granted, $this->consent->current($row->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertNull($this->consent->current($row->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates), 'An unticked box is not recorded as an opt-out.');
        $this->assertFalse(app(SuppressionService::class)->isSuppressed($row->phone_hash, ConsentChannel::Whatsapp));
        $this->assertSame('PLACEHOLDER-2026-10', ConsentRecord::query()->where('status', 'granted')->value('notice_version'));
        $this->assertTrue(AuditLog::query()->where('action', 'supporter.registered')->exists());

        foreach (['nin', 'bvn', 'pvc', 'vin', 'religion', 'ethnicity', 'date_of_birth'] as $forbidden) {
            $this->assertFalse(Schema::hasColumn('supporters', $forbidden), "{$forbidden} must not be collected");
        }
    }

    public function test_repeat_registration_deduplicates_on_the_phone_hash(): void
    {
        $first = $this->registerSupporter('08031112222', consents: ['sms' => false]);
        $second = app(SupporterRegistrar::class)->register(['full_name' => 'Different Name', 'phone' => '+234 803 111 2222', 'lga_id' => $this->lga('sapele')->id], SupporterSource::Web, ['sms' => true], true);

        $this->assertFalse($second->created);
        $this->assertTrue($first->is($second->supporter));
        $this->assertSame('Sample Person', $second->supporter->full_name, 'A repeat registration does not overwrite personal data.');
        $this->assertSame(1, Supporter::query()->count());
        $this->assertTrue($this->consent->isEligible((string) $first->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
    }

    public function test_adults_only(): void
    {
        $this->expectException(ValidationException::class);
        app(SupporterRegistrar::class)->register(['full_name' => 'X', 'phone' => '08031112222', 'lga_id' => $this->lga('udu')->id], SupporterSource::Staff, [], false);
    }

    public function test_consent_cannot_be_recorded_without_a_published_notice(): void
    {
        PrivacyNotice::query()->update(['published_at' => null]);

        $this->expectException(NoPublishedNotice::class);
        $this->registerSupporter('08031112222', consents: ['sms' => true]);
    }

    public function test_location_is_derived_from_the_most_specific_value(): void
    {
        $unit = PollingUnit::factory()->create(['ward_id' => $this->ward('bomadi')->id])->refresh();
        $registrar = app(SupporterRegistrar::class);

        $byUnit = $registrar->register(['full_name' => 'A', 'phone' => '08030000011', 'polling_unit_code' => strtolower($unit->code), 'lga_id' => $this->lga('udu')->id], SupporterSource::Staff, [], true)->supporter;
        $this->assertSame([$unit->id, $unit->ward_id, $unit->lga_id], [$byUnit->polling_unit_id, $byUnit->ward_id, $byUnit->lga_id]);

        $this->assertThrows(fn () => $registrar->register(['full_name' => 'B', 'phone' => '08030000012', 'ward_id' => $this->ward('bomadi')->id, 'lga_id' => $this->lga('udu')->id], SupporterSource::Staff, [], true), ValidationException::class);
        $this->assertThrows(fn () => $registrar->register(['full_name' => 'C', 'phone' => '08030000013', 'polling_unit_code' => 'NOPE'], SupporterSource::Staff, [], true), ValidationException::class);
    }

    public function test_consent_records_are_append_only(): void
    {
        $this->registerSupporter('08031112222');
        $id = ConsentRecord::query()->value('id');

        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('consent_records')->where('id', $id)->update(['status' => 'withdrawn'])), QueryException::class, 'append-only');
        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('consent_records')->where('id', $id)->delete()), QueryException::class, 'append-only');
    }

    public function test_the_latest_decision_applies_and_withdrawal_suppresses(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $hash = (string) $supporter->phone_hash;

        $this->consent->record($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Withdrawn, RequestChannel::Sms, $supporter);

        $this->assertFalse($this->consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertTrue(app(SuppressionService::class)->isSuppressed($hash, ConsentChannel::Sms));
        $this->assertFalse(app(SuppressionService::class)->isSuppressed($hash, ConsentChannel::Whatsapp));
        $this->assertSame(2, ConsentRecord::query()->count(), 'History is kept.');

        // A new explicit grant lifts the channel opt-out.
        $this->consent->record($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Sms, $supporter);
        $this->assertTrue($this->consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
    }

    public function test_stop_on_every_channel_suppresses_even_without_prior_consent(): void
    {
        $hash = PhoneHasher::hash('+2348039998888');

        $this->consent->withdrawAll($hash, null, RequestChannel::Sms);

        foreach (ConsentChannel::cases() as $channel) {
            $this->assertTrue(app(SuppressionService::class)->isSuppressed($hash, $channel));
        }
    }

    public function test_erasure_suppression_is_lifted_only_by_the_persons_own_new_consent(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $hash = (string) $supporter->phone_hash;
        app(ErasureService::class)->anonymise($supporter, null, 'test');

        $this->assertTrue(app(SuppressionService::class)->isSuppressed($hash, ConsentChannel::Sms));

        // Staff cannot undo an erasure request…
        $this->consent->record($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Staff);
        $this->assertFalse($this->consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));

        // …but the person re-registering on their own can.
        $this->registerSupporter('08031112222', source: SupporterSource::Web);
        $this->assertTrue($this->consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertSame(2, Supporter::query()->count(), 'The erased record stays anonymised; a new record is created.');
    }

    public function test_objection_suppressions_survive_new_consent(): void
    {
        $hash = PhoneHasher::hash('+2348031112222');
        app(SuppressionService::class)->suppress($hash, null, SuppressionReason::Objection, RequestChannel::Staff);

        $this->consent->record($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Web);

        $this->assertFalse($this->consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
    }

    public function test_suppressions_are_unique_per_channel(): void
    {
        $hash = PhoneHasher::hash('+2348031112222');
        $service = app(SuppressionService::class);

        $a = $service->suppress($hash, ConsentChannel::Sms, SuppressionReason::OptOut, RequestChannel::Sms);
        $b = $service->suppress($hash, ConsentChannel::Sms, SuppressionReason::Complaint, RequestChannel::Staff);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Suppression::query()->count());
    }

    public function test_consent_grants_extend_retention(): void
    {
        $supporter = $this->registerSupporter('08031112222', consents: []);
        $supporter->forceFill(['retention_until' => now()->addDay()->toDateString()])->save();

        $this->consent->record((string) $supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Web, $supporter);

        $this->assertTrue($supporter->refresh()->retention_until->greaterThan(now()->addMonths(35)));
    }
}
