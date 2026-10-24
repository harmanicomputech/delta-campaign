<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\DsrStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use App\Livewire\Workspace\Privacy\PrivacyCentre;
use App\Livewire\Workspace\Privacy\RequestShow;
use App\Models\DataSubjectRequest;
use App\Models\PrivacyNotice;
use App\Models\Supporter;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SuppressionService;
use App\Services\Privacy\DataSubjectRequestService;
use App\Services\Privacy\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class DataSubjectRightsTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private DataSubjectRequestService $dsr;

    private User $dpo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedSupporterContext();
        $this->dsr = app(DataSubjectRequestService::class);
        $this->dpo = User::factory()->withTwoFactor()->withRole('data_protection_officer')->create();
    }

    public function test_requests_get_a_reference_deadline_and_acknowledgement(): void
    {
        $this->registerSupporter('08031112222');

        $request = $this->dsr->submit(DsrType::Access, '0803 111 2222', RequestChannel::Sms, null, identityVerified: true);

        $this->assertMatchesRegularExpression('/^DSR-\d{2}-[A-Z0-9]{6}$/', $request->reference);
        $this->assertSame(DsrStatus::InProgress, $request->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $request->due_at->getTimestamp(), 5);
        $this->assertNotNull($request->supporter_id);
        $this->assertSame(1, $this->messagesTo('+2348031112222'));
    }

    public function test_withdrawing_consent_takes_effect_immediately(): void
    {
        $supporter = $this->registerSupporter('08031112222', consents: ['sms' => true, 'whatsapp' => true]);

        $this->dsr->submit(DsrType::WithdrawConsent, '08031112222', RequestChannel::Ussd, null, true);

        $consent = app(ConsentService::class);
        $this->assertFalse($consent->isEligible((string) $supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates));
        $this->assertFalse($consent->isEligible((string) $supporter->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates));
    }

    public function test_erasure_requests_stop_contact_at_once_and_erase_on_completion(): void
    {
        $supporter = $this->registerSupporter('08031112222');

        $request = $this->dsr->submit(DsrType::Erasure, '08031112222', RequestChannel::Staff, 'Please delete', identityVerified: false);

        $this->assertTrue(app(SuppressionService::class)->isSuppressed((string) $supporter->phone_hash, ConsentChannel::Sms));
        $this->assertTrue($supporter->refresh()->processing_restricted);
        $this->assertThrows(fn () => $this->dsr->complete($request, $this->dpo, 'Erased as requested'), ValidationException::class);

        $this->dsr->markIdentityVerified($request, $this->dpo, 'call-back');
        $this->dsr->complete($request->refresh(), $this->dpo, 'Erased as requested');

        $this->assertNotNull($supporter->refresh()->erased_at);
        $this->assertSame(DsrStatus::Completed, $request->refresh()->status);
        $this->assertThrows(fn () => $this->dsr->complete($request, $this->dpo, 'again'), ValidationException::class);
    }

    public function test_access_requests_produce_a_complete_package(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        $request = $this->dsr->submit(DsrType::Access, '08031112222', RequestChannel::Web, null, true);

        $path = $this->dsr->prepareAccessPackage($request, $this->dpo);
        $package = json_decode(Storage::disk('local')->get($path), true);

        $this->assertSame($supporter->reference, $package['supporter']['reference']);
        $this->assertSame('+2348031112222', $package['supporter']['phone']);
        $this->assertNotEmpty($package['consent_history']);
    }

    public function test_rejection_closes_the_request_and_tells_the_person(): void
    {
        $request = $this->dsr->submit(DsrType::Rectification, '08031112222', RequestChannel::Web, null, false);

        $this->dsr->reject($request, $this->dpo, 'We hold no record for this number.');

        $this->assertSame(DsrStatus::Rejected, $request->refresh()->status);
        $this->assertSame(2, $this->messagesTo('+2348031112222'));
    }

    public function test_retention_anonymises_expired_records_except_legal_holds(): void
    {
        $expired = $this->registerSupporter('08031112222');
        $held = $this->registerSupporter('08031113333', name: 'Held');
        $current = $this->registerSupporter('08031114444', name: 'Current');
        Supporter::query()->whereIn('id', [$expired->id, $held->id])->update(['retention_until' => now()->subDay()->toDateString()]);
        $held->forceFill(['legal_hold' => true])->save();

        $this->assertSame(1, app(RetentionService::class)->apply(dryRun: true));
        $this->assertNull($expired->refresh()->erased_at);

        $this->artisan('privacy:apply-retention')->expectsOutputToContain('Anonymised: 1')->assertSuccessful();
        $this->assertNotNull($expired->refresh()->erased_at);
        $this->assertNull($held->refresh()->erased_at);
        $this->assertNull($current->refresh()->erased_at);
    }

    public function test_privacy_centre_is_restricted_to_privacy_roles(): void
    {
        $this->signIn(User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create())->get('/workspace/privacy')->assertForbidden();
        $this->signIn($this->dpo)->get('/workspace/privacy')->assertOk()->assertSee(__('privacy.requests'));
    }

    public function test_the_dpo_records_and_works_requests_in_the_ui(): void
    {
        $this->registerSupporter('08031112222');
        $this->signIn($this->dpo);

        Livewire::test(PrivacyCentre::class)
            ->set('dsrType', 'objection')->set('dsrPhone', '08031112222')->set('dsrIdentityChecked', true)
            ->call('recordRequest')->assertHasNoErrors()->assertRedirect();

        $request = DataSubjectRequest::query()->firstOrFail();
        $this->get(route('workspace.privacy.requests.show', $request))->assertOk()->assertSee($request->reference);

        Livewire::test(RequestShow::class, ['request' => $request])
            ->set('resolution', 'Processing restricted and contact suppressed.')->call('complete')->assertHasNoErrors();
        $this->assertSame(DsrStatus::Completed, $request->refresh()->status);
    }

    public function test_opt_outs_cannot_be_lifted_manually(): void
    {
        $supporter = $this->registerSupporter('08031112222');
        app(ConsentService::class)->withdrawAll((string) $supporter->phone_hash, ConsentChannel::Sms, RequestChannel::Sms);
        $suppression = Suppression::query()->firstOrFail();
        $this->signIn($this->dpo);

        Livewire::test(PrivacyCentre::class)
            ->set('liftReason', 'Trying to override')->call('lift', $suppression->id);

        $this->assertNull($suppression->refresh()->lifted_at);
    }

    public function test_notices_are_drafted_then_published(): void
    {
        $this->signIn($this->dpo);

        Livewire::test(PrivacyCentre::class)
            ->set('noticeVersion', '2026-11-v1')->set('summaryEn', 'Summary')->set('bodyEn', 'Body')
            ->call('saveNotice')->assertHasNoErrors();

        $notice = PrivacyNotice::query()->where('version', '2026-11-v1')->firstOrFail();
        $this->assertNull($notice->published_at);

        Livewire::test(PrivacyCentre::class)->call('publishNotice', $notice->id);
        $this->assertSame('2026-11-v1', PrivacyNotice::current()?->version);
    }
}
