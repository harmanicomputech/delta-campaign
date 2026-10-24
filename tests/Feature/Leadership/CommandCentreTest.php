<?php

declare(strict_types=1);

namespace Tests\Feature\Leadership;

use App\Livewire\Workspace\CommandCentre\CommandCentre;
use App\Models\User;
use App\Services\Reporting\AreaMetrics;
use App\Services\Volunteers\VolunteerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class CommandCentreTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();

        foreach (range(1, 6) as $i) {
            $this->registerSupporter(sprintf('0803200%04d', $i), 'udu');
        }
        $this->registerSupporter('08032009999', 'sapele');
    }

    public function test_breakdowns_are_limited_to_the_viewers_scope_and_add_up(): void
    {
        $metrics = app(AreaMetrics::class);
        $district = User::factory()->withRole('district_coordinator', $this->lga('udu')->senatorial_district_id)->create();
        $scope = $district->geoScope('command-centre.view');

        $this->assertSame('lga', $metrics->startLevel($scope));
        $rows = $metrics->breakdown($scope, 'lga');
        $this->assertSame(8, $rows->count(), 'Delta Central has 8 LGAs');
        $this->assertSame(6, $rows->firstWhere('name', 'Udu')['supporters']);
        $this->assertSame(1, $rows->firstWhere('name', 'Sapele')['supporters']);
        $this->assertSame(7, $metrics->summary($rows)['supporters']);
        $this->assertSame(7, array_sum($metrics->trend($scope, 'lga', null)['supporters']));

        $statewide = $metrics->breakdown(User::factory()->withRole('dg')->create()->geoScope('command-centre.view'), 'district');
        $this->assertSame(3, $statewide->count());
    }

    public function test_coverage_counts_wards_with_an_active_volunteer(): void
    {
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $volunteers = app(VolunteerService::class);
        $volunteers->approve($volunteers->apply(['full_name' => 'Sample V', 'phone' => '08062220001', 'ward_id' => $this->ward('udu')->id], 'staff'), $coordinator);
        $this->ward('udu', 'W02');

        $rows = app(AreaMetrics::class)->breakdown(User::factory()->withRole('dg')->create()->geoScope(), 'ward', $this->lga('udu')->id);

        $this->assertSame(2, $rows->count());
        $this->assertSame(1, app(AreaMetrics::class)->summary($rows)['covered_wards']);
    }

    public function test_drill_down_and_back_up(): void
    {
        $dg = User::factory()->withRole('dg')->create();
        $delta = $this->lga('udu')->senatorial_district_id;

        Livewire::actingAs($dg)->test(CommandCentre::class)
            ->assertSee('Delta Central')
            ->call('drill', $delta)->assertSet('level', 'lga')->assertSee('Udu')
            ->call('drill', $this->lga('udu')->id)->assertSet('level', 'ward')->assertSee($this->ward('udu')->name)
            ->call('up')->assertSet('level', 'lga')->assertSet('parent', $delta)
            ->call('up')->assertSet('level', '');
    }

    public function test_tampered_drill_parameters_outside_scope_are_ignored(): void
    {
        $district = User::factory()->withRole('district_coordinator', $this->lga('udu')->senatorial_district_id)->create();

        Livewire::actingAs($district)->withQueryParams(['level' => 'ward', 'parent' => $this->lga('bomadi')->id])->test(CommandCentre::class)
            ->assertSet('level', '')->assertSet('parent', null)
            ->assertSee('Udu')->assertDontSee('Bomadi');
    }

    public function test_aggregate_only_roles_see_small_numbers_masked(): void
    {
        $analyst = User::factory()->withRole('analyst')->create();

        Livewire::actingAs($analyst)->withQueryParams(['level' => 'lga', 'parent' => $this->lga('sapele')->senatorial_district_id])->test(CommandCentre::class)
            ->assertSee('&lt;5', false)
            ->assertSee(__('command.masked', ['n' => 5]));

        $this->assertSame('<5', CommandCentre::figure(3, true));
        $this->assertSame('0', CommandCentre::figure(0, true));
        $this->assertSame('3', CommandCentre::figure(3, false));
        $this->assertSame('12', CommandCentre::figure(12, true));
    }

    public function test_access_requires_the_command_centre_permission(): void
    {
        $this->signIn(User::factory()->withRole('volunteer', $this->ward('udu')->id)->create())->get(route('workspace.command-centre'))->assertForbidden();
        $this->signIn(User::factory()->withRole('dg')->withTwoFactor()->create())->get(route('workspace.command-centre'))->assertOk()->assertSee(__('command.registrations'));
    }
}
