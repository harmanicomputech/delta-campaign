<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\OtpPurpose;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Livewire\Workspace\Users\UsersIndex;
use App\Models\AuditLog;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\AuthorizationDenied;
use App\Services\Users\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class UserAdministrationTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private UserAdministration $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->admin = app(UserAdministration::class);
    }

    private function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }

    private function lgaCoordinator(): User
    {
        return User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
    }

    public function test_coordinators_can_create_accounts_inside_their_area(): void
    {
        $actor = $this->lgaCoordinator();
        $ward = $this->ward('udu');

        $user = $this->admin->create($actor, 'Sample Ward Lead', '0803 555 1111', $this->role('ward_coordinator'), ScopeType::Ward, $ward->id);

        $this->assertTrue($user->hasRole('ward_coordinator'));
        $this->assertSame('+2348035551111', $user->phone);
        $this->assertSame(1, $this->messagesTo('+2348035551111'));
        $this->assertTrue(AuditLog::query()->where('action', 'user.created')->where('actor_id', $actor->id)->exists());
    }

    public function test_assignments_outside_the_actors_area_are_refused(): void
    {
        $this->expectException(AuthorizationDenied::class);

        $this->admin->create($this->lgaCoordinator(), 'X', '0803 555 2222', $this->role('ward_coordinator'), ScopeType::Ward, $this->ward('sapele')->id);
    }

    public function test_roles_with_permissions_the_actor_lacks_cannot_be_granted(): void
    {
        $actor = $this->lgaCoordinator();
        $assignable = $this->admin->assignableRoles($actor)->pluck('slug');

        $this->assertContains('ward_coordinator', $assignable);
        $this->assertContains('volunteer', $assignable);
        $this->assertNotContains('dg', $assignable);
        $this->assertNotContains('data_protection_officer', $assignable);
        $this->assertNotContains('district_coordinator', $assignable, 'district coordinators hold command-centre.view, which LGA coordinators lack');
        $this->assertNotContains('super_admin', $assignable);
    }

    public function test_privileged_roles_need_the_privileged_assignment_permission(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $super = User::factory()->withTwoFactor()->withRole('super_admin')->create();

        $this->assertNotContains('dg', $this->admin->assignableRoles($director)->pluck('slug'));
        $this->assertContains('dg', $this->admin->assignableRoles($super)->pluck('slug'));
        $this->assertContains('super_admin', $this->admin->assignableRoles($super)->pluck('slug'));
    }

    public function test_scope_type_must_match_the_roles_level(): void
    {
        $super = User::factory()->withTwoFactor()->withRole('super_admin')->create();
        $target = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->admin->assign($super, $target, $this->role('ward_coordinator'), ScopeType::Lga, $this->lga('udu')->id);
    }

    public function test_duplicate_numbers_are_rejected(): void
    {
        User::factory()->phone('+2348035553333')->create();

        $this->expectException(ValidationException::class);
        $this->admin->create($this->lgaCoordinator(), 'Dup', '08035553333', $this->role('volunteer'), ScopeType::Ward, $this->ward('udu')->id);
    }

    public function test_revoking_a_role_removes_permissions_immediately(): void
    {
        $actor = $this->lgaCoordinator();
        $target = User::factory()->withRole('ward_coordinator', $this->ward('udu')->id)->create();
        $this->signIn($target)->get('/workspace/users')->assertOk();

        $this->admin->revoke($actor, $target->activeAssignments()->firstOrFail());

        // A new request reloads the account, exactly as in production.
        $this->signIn($target->fresh())->get('/workspace/users')->assertForbidden();
        $this->assertTrue(AuditLog::query()->where('action', 'user.role_revoked')->exists());
    }

    public function test_the_last_super_admin_cannot_remove_their_own_role(): void
    {
        $super = User::factory()->withTwoFactor()->withRole('super_admin')->create();

        $this->expectException(ValidationException::class);
        $this->admin->revoke($super, $super->activeAssignments()->with('role')->firstOrFail());
    }

    public function test_suspension_signs_the_person_out_everywhere(): void
    {
        $actor = $this->lgaCoordinator();
        $target = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        DB::table('sessions')->insert(['id' => 'sx', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

        $this->admin->suspend($actor, $target, 'Sample reason for suspension');

        $this->assertSame(UserStatus::Suspended, $target->refresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'user.suspended')->exists());

        $this->admin->reactivate($actor, $target);
        $this->assertTrue($target->refresh()->isActive());
    }

    public function test_accounts_cannot_suspend_themselves_or_out_of_scope_people(): void
    {
        $actor = $this->lgaCoordinator();
        $outside = User::factory()->withRole('volunteer', $this->ward('sapele')->id)->create();

        $this->assertThrows(fn () => $this->admin->suspend($actor, $actor, 'nope nope'), ValidationException::class);
        $this->assertThrows(fn () => $this->admin->suspend($actor, $outside, 'nope nope'), AuthorizationDenied::class);
    }

    public function test_privileged_accounts_are_managed_only_by_privileged_assigners(): void
    {
        $director = User::factory()->withTwoFactor()->withRole('state_director')->create();
        $dpo = User::factory()->withTwoFactor()->withRole('data_protection_officer')->create();

        $this->assertFalse($this->admin->canManage($director, $dpo));
    }

    public function test_phone_number_recovery_requires_the_code_from_the_new_number(): void
    {
        $actor = $this->lgaCoordinator();
        $target = User::factory()->phone('+2348031110000')->withRole('volunteer', $this->ward('udu')->id)->create();

        $challenge = $this->admin->beginPhoneChange($actor, $target, '0803 222 0000');
        $code = $this->lastCodeSentTo('+2348032220000');
        $this->assertSame(OtpPurpose::PhoneChange, $challenge->purpose);

        $this->assertThrows(fn () => $this->admin->completePhoneChange($actor, $target, $challenge, '0803 222 0000', '000001'), ValidationException::class);
        $this->admin->completePhoneChange($actor, $target, $challenge->refresh(), '0803 222 0000', (string) $code);

        $this->assertSame('+2348032220000', $target->refresh()->phone);
        $this->assertSame(1, $this->messagesTo('+2348031110000') - 0, 'The old number is told about the change.');
        $this->assertNull(User::findByPhone('+2348031110000'));
    }

    public function test_users_page_creates_accounts_through_livewire(): void
    {
        $actor = $this->lgaCoordinator();
        $ward = $this->ward('udu');
        $this->signIn($actor);

        Livewire::test(UsersIndex::class)
            ->set('name', 'Sample Volunteer')
            ->set('phone', '08034440000')
            ->set('roleId', $this->role('volunteer')->id)
            ->set('lgaId', $ward->lga_id)
            ->set('wardId', $ward->id)
            ->call('create')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertNotNull(User::findByPhone('08034440000'));
    }

    public function test_challenges_for_other_accounts_cannot_complete_a_phone_change(): void
    {
        $actor = $this->lgaCoordinator();
        $a = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $b = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $challenge = $this->admin->beginPhoneChange($actor, $a, '0803 999 0001');
        $code = $this->lastCodeSentTo('+2348039990001');

        $this->assertThrows(fn () => $this->admin->completePhoneChange($actor, $b, $challenge, '0803 999 0001', (string) $code), ValidationException::class);
        $this->assertInstanceOf(OtpChallenge::class, $challenge);
    }

    public function test_the_create_admin_command_bootstraps_a_super_admin_idempotently(): void
    {
        $this->artisan('campaign:create-admin', ['phone' => '0803 000 0009', 'name' => 'Sample Admin'])->assertSuccessful();
        $this->artisan('campaign:create-admin', ['phone' => '0803 000 0009', 'name' => 'Sample Admin'])->assertSuccessful();
        $this->artisan('campaign:create-admin', ['phone' => '123', 'name' => 'Bad'])->assertFailed();

        $user = User::findByPhone('08030000009');
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertSame(1, $user->activeAssignments()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'user.super_admin_granted')->where('actor_label', 'console')->exists());
    }
}
