<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\ScopeType;
use App\Livewire\Workspace\Users\UsersIndex;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Ward;
use App\Services\Authorization\GeoScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class GeographicScopeTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private Ward $uduWard;

    private Ward $otherUduWard;

    private Ward $sapeleWard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->uduWard = $this->ward('udu', 'W01');
        $this->otherUduWard = $this->ward('udu', 'W02');
        $this->sapeleWard = $this->ward('sapele', 'W01');
        foreach ([$this->uduWard, $this->otherUduWard, $this->sapeleWard] as $ward) {
            PollingUnit::factory()->count(2)->create(['ward_id' => $ward->id]);
        }
    }

    public function test_ward_scope_sees_only_its_ward(): void
    {
        $user = User::factory()->withRole('ward_coordinator', $this->uduWard->id)->create();

        $this->assertSame([$this->uduWard->id], Ward::query()->visibleTo($user)->pluck('id')->all());
        $this->assertSame(2, PollingUnit::query()->visibleTo($user)->count());
        $this->assertTrue(PollingUnit::query()->visibleTo($user)->get()->every(fn ($pu) => $pu->ward_id === $this->uduWard->id));
        $this->assertSame([], Lga::query()->visibleTo($user)->pluck('id')->all(), 'A ward scope does not grant the whole LGA.');
    }

    public function test_lga_scope_covers_every_ward_and_unit_in_it(): void
    {
        $user = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $this->assertEqualsCanonicalizing([$this->uduWard->id, $this->otherUduWard->id], Ward::query()->visibleTo($user)->pluck('id')->all());
        $this->assertSame(4, PollingUnit::query()->visibleTo($user)->count());
    }

    public function test_district_scope_covers_its_lgas(): void
    {
        $central = $this->lga('udu')->senatorial_district_id;
        $user = User::factory()->withRole('district_coordinator', $central)->create();

        $this->assertSame(8, Lga::query()->visibleTo($user)->count());
        $this->assertSame(6, PollingUnit::query()->visibleTo($user)->count(), 'Udu and Sapele are both in Delta Central.');
    }

    public function test_statewide_roles_see_everything_and_unassigned_users_see_nothing(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $nobody = User::factory()->create();

        $this->assertSame(6, PollingUnit::query()->visibleTo($director)->count());
        $this->assertSame(0, PollingUnit::query()->visibleTo($nobody)->count());
        $this->assertTrue($nobody->geoScope()->isEmpty());
    }

    public function test_scope_is_per_permission(): void
    {
        // Volunteer in one ward and LGA coordinator in another LGA: users.view only comes from the coordinator role.
        $user = User::factory()->withRole('volunteer', $this->sapeleWard->id)->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $this->assertSame([$this->lga('udu')->id], $user->geoScope('users.view')->lgaIds);
        $this->assertSame([], $user->geoScope('users.view')->wardIds);
        $this->assertContains($this->sapeleWard->id, $user->geoScope()->wardIds);
    }

    public function test_covers_checks_the_whole_ancestry(): void
    {
        $scope = new GeoScope(lgaIds: [$this->lga('udu')->id]);
        $unit = PollingUnit::query()->where('ward_id', $this->uduWard->id)->firstOrFail();
        $foreign = PollingUnit::query()->where('ward_id', $this->sapeleWard->id)->firstOrFail();

        $this->assertTrue($scope->covers(ScopeType::Ward, $this->uduWard->id));
        $this->assertTrue($scope->covers(ScopeType::PollingUnit, $unit->id));
        $this->assertFalse($scope->covers(ScopeType::PollingUnit, $foreign->id));
        $this->assertFalse($scope->covers(ScopeType::Lga, $this->lga('sapele')->id));
        $this->assertFalse($scope->covers(ScopeType::State, null));
        $this->assertFalse($scope->covers(ScopeType::Ward, 999_999));
    }

    public function test_users_outside_the_viewers_scope_are_invisible(): void
    {
        $viewer = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $inside = User::factory()->withRole('ward_coordinator', $this->uduWard->id)->create();
        $outside = User::factory()->withRole('ward_coordinator', $this->sapeleWard->id)->create();
        $statewide = User::factory()->withRole('content_editor')->create();
        $straddling = User::factory()->withRole('ward_coordinator', $this->uduWard->id)->withRole('ward_coordinator', $this->sapeleWard->id)->create();

        $visible = User::query()->visibleTo($viewer)->pluck('id')->all();

        $this->assertContains($inside->id, $visible);
        $this->assertContains($viewer->id, $visible);
        $this->assertNotContains($outside->id, $visible);
        $this->assertNotContains($statewide->id, $visible);
        $this->assertNotContains($straddling->id, $visible, 'People with any role outside the viewer\'s area are not shown.');
    }

    public function test_url_manipulation_cannot_reach_out_of_scope_users(): void
    {
        $viewer = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $outside = User::factory()->withRole('ward_coordinator', $this->sapeleWard->id)->create();
        $inside = User::factory()->withRole('ward_coordinator', $this->uduWard->id)->create();

        $this->signIn($viewer)->get(route('workspace.users.show', $inside))->assertOk();
        $this->get(route('workspace.users.show', $outside))->assertNotFound();
    }

    public function test_search_never_reveals_out_of_scope_users(): void
    {
        $viewer = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $outside = User::factory()->phone('+2348077777777')->withRole('ward_coordinator', $this->sapeleWard->id)->create();
        $this->signIn($viewer);

        Livewire::test(UsersIndex::class)
            ->set('search', '08077777777')->assertDontSee($outside->name)
            ->set('search', $outside->name)->assertDontSee($outside->maskedPhone());
    }

    public function test_permission_checks_go_through_the_gate(): void
    {
        $analyst = User::factory()->withRole('analyst')->create();
        $suspended = User::factory()->suspended()->withRole('super_admin')->create();

        $this->assertTrue($analyst->can('reports.view'));
        $this->assertFalse($analyst->can('supporters.view'), 'Analysts see aggregates only.');
        $this->assertFalse($suspended->can('users.view'), 'Suspended accounts are denied everything.');
        $this->signIn($analyst)->get('/workspace/users')->assertForbidden();
    }

    public function test_every_role_references_known_permissions(): void
    {
        $known = collect(config('permissions.permissions'))->flatMap(fn ($group) => array_keys($group))->all();

        foreach (config('permissions.roles') as $slug => $role) {
            foreach ($role['permissions'] as $permission) {
                $this->assertTrue($permission === '*' || in_array($permission, $known, true), "{$slug}: {$permission}");
            }
        }
    }
}
