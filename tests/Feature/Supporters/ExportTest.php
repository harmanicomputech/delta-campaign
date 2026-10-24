<?php

declare(strict_types=1);

namespace Tests\Feature\Supporters;

use App\Enums\ExportStatus;
use App\Livewire\Workspace\Exports\ExportsIndex;
use App\Models\AuditLog;
use App\Models\ExportJob;
use App\Models\User;
use App\Services\Exports\SupporterExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class ExportTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    private SupporterExportService $exports;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedSupporterContext();
        $this->exports = app(SupporterExportService::class);
        $this->registerSupporter('08031112222', name: '=HYPERLINK("evil")');
        $this->registerSupporter('08031113333', 'sapele', name: 'Sapele Person');
    }

    private function csv(ExportJob $export): string
    {
        return (string) Storage::disk('local')->get((string) $export->refresh()->path);
    }

    public function test_exports_are_scoped_masked_and_formula_safe(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $district = User::factory()->withRole('district_coordinator', $this->lga('udu')->senatorial_district_id)->create();

        $this->assertThrows(fn () => $this->exports->request($district, [], false), AuthorizationException::class, 'not permitted');

        $export = $this->exports->request($director, ['lga_id' => $this->lga('udu')->id], false);
        $csv = $this->csv($export);

        $this->assertSame(ExportStatus::Completed, $export->status);
        $this->assertSame(1, $export->row_count);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('+234 ••• ••• 2222', $csv);
        $this->assertStringNotContainsString('+2348031112222', $csv);
        $this->assertStringNotContainsString('Sapele Person', $csv);
        $this->assertTrue(AuditLog::query()->where('action', 'export.requested')->exists());
    }

    public function test_full_numbers_need_the_contact_permission(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $this->assertThrows(fn () => $this->exports->request($director, [], true), AuthorizationException::class);

        $super = User::factory()->withTwoFactor()->withRole('super_admin')->create();
        $this->assertStringContainsString('+2348031112222', $this->csv($this->exports->request($super, [], true)));
    }

    public function test_permission_is_rechecked_when_the_job_runs(): void
    {
        Bus::fake();
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $export = $this->exports->request($director, [], false);

        $director->activeAssignments()->update(['revoked_at' => now()]);
        $this->exports->generate($export->refresh());

        $this->assertSame(ExportStatus::Failed, $export->refresh()->status);
        $this->assertNull($export->path);
    }

    public function test_downloads_are_signed_requester_only_and_expire(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $other = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $export = $this->exports->request($director, [], false);
        $url = URL::temporarySignedRoute('workspace.exports.download', now()->addMinutes(10), ['export' => $export->id]);

        $this->signIn($director)->get(route('workspace.exports.download', $export))->assertForbidden();
        $this->get($url)->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->assertSame(1, $export->refresh()->download_count);
        $this->assertTrue(AuditLog::query()->where('action', 'export.downloaded')->exists());

        $this->signIn($other)->get($url)->assertNotFound();

        $this->travel(25)->hours();
        $this->signIn($director)->get(URL::temporarySignedRoute('workspace.exports.download', now()->addMinutes(10), ['export' => $export->id]))->assertStatus(410);
        $this->assertSame(1, $this->exports->prune());
        Storage::disk('local')->assertMissing('exports/'.$export->id.'.csv');
    }

    public function test_exports_page_lists_only_my_exports(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $this->exports->request($director, [], false);
        $this->exports->request(User::factory()->withTwoFactor()->withRole('state_director')->create(), [], false);

        $this->signIn($director)->get('/workspace/exports')->assertOk()->assertSee(__('supporters.download'));
        $this->assertSame(1, Livewire::test(ExportsIndex::class)->viewData('exports')->total());
    }
}
