<?php

declare(strict_types=1);

namespace Tests\Feature\Leadership;

use App\Enums\ImportStatus;
use App\Livewire\Workspace\Approvals\ApprovalsInbox;
use App\Livewire\Workspace\Geography\GeographyIndex;
use App\Livewire\Workspace\Geography\ImportShow;
use App\Livewire\Workspace\Geography\ImportsIndex;
use App\Livewire\Workspace\Settings\SettingsPage;
use App\Models\AuditLog;
use App\Models\ImportJob;
use App\Models\PollingUnit;
use App\Models\User;
use App\Services\Authentication\SignInService;
use App\Services\Settings\Settings;
use App\Services\Tasks\TaskService;
use App\Services\Volunteers\VolunteerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class AdministrationTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    private function confirmedAt(int $timestamp): void
    {
        session([SignInService::SESSION_PIN_CONFIRMED => $timestamp]);
    }

    private function director(): User
    {
        return User::factory()->withRole('state_director')->withTwoFactor()->create();
    }

    public function test_the_approvals_inbox_shows_only_queues_and_items_in_scope(): void
    {
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $volunteers = app(VolunteerService::class);
        $volunteers->apply(['full_name' => 'Sample Udu Applicant', 'phone' => '08063330001', 'ward_id' => $this->ward('udu')->id], 'staff');
        $volunteers->apply(['full_name' => 'Sample Sapele Applicant', 'phone' => '08063330002', 'ward_id' => $this->ward('sapele')->id], 'staff');

        $task = app(TaskService::class)->create($coordinator, ['title' => 'Sample escalated task', 'lga_id' => $this->lga('udu')->id]);
        $task->forceFill(['escalation_level' => 1])->save();

        Livewire::actingAs($coordinator)->test(ApprovalsInbox::class)
            ->assertSee('Sample Udu Applicant')->assertDontSee('Sample Sapele Applicant')
            ->assertSee('Sample escalated task')
            ->assertSee(__('approvals.volunteers'))->assertDontSee(__('approvals.dsr'))->assertDontSee(__('approvals.imports'));

        $this->signIn(User::factory()->withRole('volunteer', $this->ward('udu')->id)->create())->get(route('workspace.approvals'))->assertForbidden();
    }

    public function test_settings_are_validated_audited_and_need_a_recent_pin(): void
    {
        $admin = User::factory()->withRole('super_admin')->withTwoFactor()->create();

        $this->confirmedAt(now()->getTimestamp());
        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('values.privacy__retention_months', 2)->call('save', 'privacy.retention_months')->assertHasErrors('values.privacy__retention_months')
            ->set('values.privacy__retention_months', 24)->call('save', 'privacy.retention_months')->assertHasNoErrors();

        $this->assertSame(24, app(Settings::class)->get('privacy.retention_months'));
        $entry = AuditLog::query()->where('action', 'settings.changed')->sole();
        $this->assertEquals(['from' => 36, 'to' => 24], $entry->changes);

        $this->confirmedAt(now()->subHour()->getTimestamp());
        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('values.privacy__dsr_response_days', 20)->call('save', 'privacy.dsr_response_days')->assertRedirect(route('pin.confirm'));
        $this->assertSame(30, app(Settings::class)->get('privacy.dsr_response_days'));

        $this->signIn($this->director())->get(route('workspace.settings'))->assertForbidden();
    }

    public function test_the_geography_browser_respects_scope(): void
    {
        $udu = $this->ward('udu');
        $sapele = $this->ward('sapele');
        PollingUnit::query()->create(['ward_id' => $udu->id, 'code' => 'SMP-UDU-1', 'name' => 'Sample Udu School', 'is_sample' => true]);
        PollingUnit::query()->create(['ward_id' => $sapele->id, 'code' => 'SMP-SAP-1', 'name' => 'Sample Sapele Hall', 'is_sample' => true]);
        $ward = User::factory()->withRole('ward_coordinator', $udu->id)->create();

        Livewire::actingAs($ward)->test(GeographyIndex::class)
            ->assertSee('Udu')->assertDontSee('Sapele')
            ->call('open', 'lga', $this->lga('udu')->id)->assertSee($udu->name)
            ->call('open', 'ward', $udu->id)->assertSee('SMP-UDU-1')
            ->set('search', 'SMP-')->assertSee('SMP-UDU-1')->assertDontSee('SMP-SAP-1');

        Livewire::actingAs($ward)->withQueryParams(['ward' => $sapele->id])->test(GeographyIndex::class)->assertDontSee('SMP-SAP-1');
    }

    public function test_imports_are_uploaded_previewed_and_confirmed_through_the_ui(): void
    {
        Storage::fake('local');
        $director = $this->director();
        $csv = "lga,ward_code,ward_name,pu_code,pu_name\nUdu,UD01,Sample Ward One,SMP-UI-001,Sample Unit One\nUdu,UD01,Sample Ward One,SMP-UI-002,Sample Unit Two\nNowhere,X1,Bad,SMP-UI-003,Bad Unit\n";

        Livewire::actingAs($director)->test(ImportsIndex::class)
            ->set('file', UploadedFile::fake()->createWithContent('units.csv', $csv))
            ->assertRedirect();

        $job = ImportJob::query()->sole();
        $this->assertSame(ImportStatus::Validated, $job->status);
        $this->assertSame([3, 2, 1], [$job->total_rows, $job->valid_rows, $job->invalid_rows]);
        $this->assertSame(0, PollingUnit::query()->count(), 'Nothing is written before confirmation');

        $this->signIn($director)->get(route('workspace.geography.imports.report', $job))->assertOk();
        $this->signIn($director)->get(route('workspace.geography.imports.template'))->assertOk()->assertSee('pu_code');

        $this->confirmedAt(now()->subHour()->getTimestamp());
        Livewire::actingAs($director)
            ->test(ImportShow::class, ['import' => $job])->call('confirm')->assertRedirect(route('pin.confirm'));
        $this->assertSame(ImportStatus::Validated, $job->refresh()->status);

        $this->confirmedAt(now()->getTimestamp());
        Livewire::actingAs($director)
            ->test(ImportShow::class, ['import' => $job])
            ->set('filter', 'invalid')->assertSee('SMP-UI-003')->assertDontSee('SMP-UI-001')
            ->call('confirm')->assertDispatched('toast', type: 'success');

        $this->assertSame(ImportStatus::Completed, $job->refresh()->status);
        $this->assertSame(2, PollingUnit::query()->count());

        $this->signIn(User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create())->get(route('workspace.geography.imports'))->assertForbidden();
    }

    public function test_the_dashboard_adapts_to_the_role(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $this->signIn($volunteer)->get(route('workspace.dashboard'))->assertOk()
            ->assertSee(__('workspace.my_tasks'))->assertDontSee(__('command.needs_decision'));

        $this->signIn($this->director())->get(route('workspace.dashboard'))->assertOk()
            ->assertSee(__('command.needs_decision'))->assertSee(__('command.coverage'));
    }
}
