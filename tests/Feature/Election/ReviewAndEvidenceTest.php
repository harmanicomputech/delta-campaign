<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\OfficialSource;
use App\Enums\ResultStatus;
use App\Livewire\Workspace\Election\UnitShow;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\Authorization\GeoScope;
use App\Services\Election\ElectionMetrics;
use App\Services\Election\EvidencePackService;
use App\Services\Election\EvidenceStore;
use App\Services\Election\FieldReports;
use App\Services\Election\IncidentService;
use App\Services\Election\ResultReview;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ZipArchive;

final class ReviewAndEvidenceTest extends ElectionTestCase
{
    private function submitted(string $code = 'SMP-001', array $scores = ['SPA' => 60, 'SPB' => 40], string $phone = '08061230001'): ResultSubmission
    {
        $assignment = $this->agent($this->unit('udu', $code), $phone);

        return app(FieldReports::class)->result($assignment, $assignment->user, $this->figures(), $scores, 'web', [$this->photo()]);
    }

    public function test_verification_needs_a_second_person_and_clean_arithmetic(): void
    {
        $submission = $this->submitted();
        $review = app(ResultReview::class);
        $verifier = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        try {
            $review->verify($submission, $submission->submitter);
            $this->fail('Agents cannot verify their own sheet.');
        } catch (AuthorizationDenied) {
        }

        $review->verify($submission, $verifier);
        $this->assertSame(ResultStatus::Verified, $submission->fresh()->status);

        $bad = $this->submitted('SMP-002', ['SPA' => 1, 'SPB' => 1], '08061230002');
        $this->expectException(ValidationException::class);
        $review->verify($bad, $verifier);
    }

    public function test_verifiers_outside_the_area_are_refused(): void
    {
        $submission = $this->submitted();
        $this->expectException(AuthorizationDenied::class);
        app(ResultReview::class)->verify($submission, User::factory()->withRole('lga_coordinator', $this->lga('warri-south')->id)->create());
    }

    public function test_official_figures_need_a_cited_inec_source_and_are_compared_with_the_verified_sheet(): void
    {
        $submission = $this->submitted();
        $review = app(ResultReview::class);
        $review->verify($submission, $this->director);
        $unit = $submission->pollingUnit;

        try {
            $review->recordOfficial($this->director, $this->election, $unit, OfficialSource::Irev, ' ', $this->figures(), ['SPA' => 60, 'SPB' => 40]);
            $this->fail('A source is required.');
        } catch (ValidationException) {
        }

        $analyst = User::factory()->withRole('analyst')->create();
        try {
            $review->recordOfficial($analyst, $this->election, $unit, OfficialSource::Irev, 'https://example.org/irev/sample', $this->figures(), ['SPA' => 60, 'SPB' => 40]);
            $this->fail('Only results.official may record official figures.');
        } catch (AuthorizationDenied) {
        }

        $official = $review->recordOfficial($this->director, $this->election, $unit, OfficialSource::Irev, 'https://example.org/irev/sample', $this->figures(), ['SPA' => 55, 'SPB' => 45]);
        $comparison = $review->compare($submission->fresh(), $official);
        $this->assertSame('differs', $comparison['status']);
        $this->assertSame(-5, collect($comparison['rows'])->firstWhere('field', 'SPA')['difference']);
        $this->assertSame([$unit->id], app(ElectionMetrics::class)->discrepantUnits($this->election, GeoScope::statewideScope()));
    }

    public function test_metrics_keep_verified_and_official_tallies_apart_and_respect_scope(): void
    {
        $a = $this->submitted('SMP-001', ['SPA' => 60, 'SPB' => 40]);
        $this->submitted('SMP-002', ['SPA' => 50, 'SPB' => 50], '08061230002');
        app(ResultReview::class)->verify($a, $this->director);
        $metrics = app(ElectionMetrics::class);

        $summary = $metrics->summary($this->election, GeoScope::statewideScope());
        $this->assertSame(2, $summary['assigned']);
        $this->assertSame(2, $summary['results']);
        $this->assertSame(1, $summary['verified']);
        $this->assertSame(['SPA' => 60, 'SPB' => 40], $metrics->verifiedTally($this->election, GeoScope::statewideScope())['scores']);
        $this->assertSame(0, $metrics->officialTally($this->election, GeoScope::statewideScope())['units']);
        $this->assertSame(0, $metrics->summary($this->election, new GeoScope(lgaIds: [$this->lga('warri-south')->id]))['results']);
    }

    public function test_evidence_is_immutable_and_tampering_is_detected(): void
    {
        $submission = $this->submitted();
        $file = $submission->evidence()->firstOrFail();
        $store = app(EvidenceStore::class);

        $this->assertTrue($store->verify($file));
        Storage::disk($file->disk)->put($file->path, 'tampered');
        $this->assertFalse($store->verify($file->fresh()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'evidence.integrity_failed']);

        $this->expectException(\LogicException::class);
        $file->fresh()->forceFill(['sha256' => str_repeat('0', 64)])->save();
    }

    public function test_evidence_downloads_are_scoped_and_audited(): void
    {
        $file = $this->submitted()->evidence()->firstOrFail();

        $this->signIn($this->director)->get(route('workspace.election.evidence', $file))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'evidence.accessed']);

        $outsider = User::factory()->withRole('lga_coordinator', $this->lga('warri-south')->id)->create();
        $this->signIn($outsider)->get(route('workspace.election.evidence', $file))->assertNotFound();
    }

    public function test_incident_workflow_is_limited_to_the_area(): void
    {
        $assignment = $this->agent($this->unit());
        $incident = app(FieldReports::class)->incident($assignment, $assignment->user, IncidentCategory::Materials, IncidentSeverity::Medium, 'Sample missing materials', 'web');
        $incidents = app(IncidentService::class);
        $local = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $incidents->acknowledge($incident, $local);
        $incidents->resolve($incident->fresh(), $local, 'Sample resolution');
        $this->assertSame('resolved', $incident->fresh()->status->value);

        $this->expectException(AuthorizationDenied::class);
        $incidents->reopen($incident->fresh(), User::factory()->withRole('lga_coordinator', $this->lga('warri-south')->id)->create(), 'Sample');
    }

    public function test_evidence_pack_contains_files_with_matching_hashes_and_a_notice(): void
    {
        $submission = $this->submitted();
        $pack = app(EvidencePackService::class)->request($this->director, $this->election, 'lga', $this->lga('udu')->id);
        $pack->refresh();
        $this->assertSame('ready', $pack->status);
        $this->assertSame(1, $pack->file_count);

        $local = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($local, Storage::disk($pack->disk)->get($pack->path));
        $this->assertSame($pack->sha256, hash_file('sha256', $local));
        $zip = new ZipArchive;
        $zip->open($local);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertStringContainsString('NOT official', $manifest['notice']);
        $entry = $manifest['files'][0];
        $this->assertSame($submission->evidence()->first()->sha256, hash('sha256', (string) $zip->getFromName($entry['path'])));
        $this->assertTrue($entry['integrity_ok']);
        $zip->close();
        @unlink($local);

        $url = URL::temporarySignedRoute('workspace.election.packs.download', now()->addMinutes(5), $pack);
        $this->signIn($this->director)->get($url)->assertOk();
        $this->signIn(User::factory()->withRole('super_admin')->withTwoFactor()->create())->get($url)->assertNotFound();
    }

    public function test_evidence_packs_are_limited_to_the_requesters_area(): void
    {
        $coordinator = User::factory()->withRole('state_director')->create();
        $lgaCoordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $this->assertNotNull(app(EvidencePackService::class)->request($coordinator, $this->election, 'state', null));

        $this->expectException(AuthorizationDenied::class);
        app(EvidencePackService::class)->request($lgaCoordinator, $this->election, 'lga', $this->lga('warri-south')->id);
    }

    public function test_unit_page_lets_a_verifier_verify_and_record_official_figures(): void
    {
        $submission = $this->submitted();
        $this->signIn($this->director);

        Livewire::test(UnitShow::class, ['unit' => $submission->pollingUnit])
            ->assertSee($submission->reference)
            ->call('verify', $submission->id)
            ->set('source', 'inec_irev')->set('reference', 'https://example.org/irev/sample')
            ->set('offValid', '100')->set('offRejected', '5')->set('offTotal', '105')->set('offScores.SPA', '60')->set('offScores.SPB', '40')
            ->call('saveOfficial')->assertHasNoErrors()
            ->assertSee(__('election.compare_matches'));
    }

    public function test_staff_screens_require_election_permissions(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        foreach (['workspace.election.hub', 'workspace.election.incidents.index', 'workspace.election.results', 'workspace.election.agents'] as $route) {
            $this->signIn($volunteer)->get(route($route))->assertForbidden();
            $this->signIn($this->director)->get(route($route))->assertOk();
        }
    }
}
