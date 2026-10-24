<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOperations;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Enums\VolunteerStatus;
use App\Livewire\Workspace\Volunteers\VolunteerShow;
use App\Livewire\Workspace\Volunteers\VolunteersIndex;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\Users\AuthorizationDenied;
use App\Services\Volunteers\VolunteerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class VolunteerLifecycleTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private VolunteerService $volunteers;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->volunteers = app(VolunteerService::class);
        $this->coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
    }

    private function application(string $phone = '08061230001', string $lga = 'udu'): Volunteer
    {
        return $this->volunteers->apply(['full_name' => 'Sample  Applicant', 'phone' => $phone, 'ward_id' => $this->ward($lga)->id, 'skills' => ['canvassing', 'not-a-skill'], 'availability' => ['weekend']], 'staff');
    }

    public function test_applications_are_deduplicated_by_phone_and_filter_unknown_values(): void
    {
        $first = $this->application();
        $again = $this->volunteers->apply(['full_name' => 'Different Name', 'phone' => '+234 806 123 0001', 'ward_id' => $this->ward('udu')->id], 'web');

        $this->assertTrue($first->is($again));
        $this->assertSame(1, Volunteer::query()->count());
        $this->assertSame('Sample Applicant', $first->full_name);
        $this->assertSame(['canvassing'], $first->skills);
        $this->assertSame(VolunteerStatus::Applied, $first->status);
        $this->assertStringNotContainsString('8061230001', (string) $first->getRawOriginal('phone_encrypted'));
    }

    public function test_approval_creates_a_ward_scoped_volunteer_account_and_sends_a_welcome_sms(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);

        $user = $volunteer->user;
        $this->assertSame(VolunteerStatus::Approved, $volunteer->status);
        $this->assertTrue($user->hasRole('volunteer'));
        $this->assertTrue($user->hasPermission('tasks.work'));
        $this->assertTrue($user->geoScope('tasks.work')->covers(ScopeType::Ward, $volunteer->ward_id));
        $this->assertFalse($user->hasPermission('supporters.view'));
        $this->assertSame(1, $this->messagesTo('+2348061230001'));
    }

    public function test_coordinators_cannot_decide_applications_outside_their_area(): void
    {
        $elsewhere = $this->application('08061230002', 'sapele');

        $this->expectException(AuthorizationDenied::class);
        $this->volunteers->approve($elsewhere, $this->coordinator);
    }

    public function test_rejection_needs_a_pending_application(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);

        $this->expectException(ValidationException::class);
        $this->volunteers->reject($volunteer, $this->coordinator, 'Too late');
    }

    public function test_suspension_removes_field_access_and_reactivation_restores_it(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);

        $this->volunteers->suspend($volunteer, $this->coordinator, 'Conduct review');
        $user = $volunteer->user->refresh();
        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertFalse($user->hasPermission('tasks.work'));

        $this->volunteers->reactivate($volunteer->refresh(), $this->coordinator);
        $user->refresh()->flushAuthorizationCache();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->hasPermission('tasks.work'));
        $this->assertSame(VolunteerStatus::Approved, $volunteer->refresh()->status);
    }

    public function test_suspension_keeps_the_account_when_the_person_has_other_roles(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);
        RoleAssignment::query()->create(['user_id' => $volunteer->user_id, 'role_id' => Role::query()->where('slug', 'content_editor')->value('id'), 'scope_type' => 'state']);

        $this->volunteers->suspend($volunteer, $this->coordinator, 'Stepping back');

        $user = $volunteer->user->refresh();
        $user->flushAuthorizationCache();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertFalse($user->hasRole('volunteer'));
        $this->assertTrue($user->hasRole('content_editor'));
    }

    public function test_reassignment_moves_the_role_to_the_new_ward(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);
        $newWard = $this->ward('udu', 'W02');

        $this->volunteers->reassign($volunteer, $newWard, $this->coordinator);

        $user = $volunteer->user->refresh();
        $user->flushAuthorizationCache();
        $this->assertSame($newWard->id, $volunteer->refresh()->ward_id);
        $this->assertSame([$newWard->id], $user->activeAssignments()->pluck('scope_id')->all());
    }

    public function test_the_list_only_shows_volunteers_in_scope_and_detail_pages_404_outside_it(): void
    {
        $mine = $this->application('08061230003');
        $other = $this->application('08061230004', 'sapele');

        Livewire::actingAs($this->coordinator)->test(VolunteersIndex::class)
            ->assertSee($mine->maskedPhone())
            ->assertDontSee($other->maskedPhone())
            ->call('approve', $mine->id)
            ->assertHasNoErrors();

        $this->assertSame(VolunteerStatus::Approved, $mine->refresh()->status);

        $this->signIn($this->coordinator)->get(route('workspace.volunteers.show', $other))->assertNotFound();
        $this->signIn($this->coordinator)->get(route('workspace.volunteers.show', $mine))->assertOk()->assertSee('Sample Applicant');
    }

    public function test_staff_can_record_an_application_only_for_a_ward_in_their_area(): void
    {
        Livewire::actingAs($this->coordinator)->test(VolunteersIndex::class)
            ->set('fullName', 'Sample New Volunteer')->set('phone', '08061230005')
            ->set('formLgaId', $this->lga('sapele')->id)->set('formWardId', $this->ward('sapele')->id)
            ->call('addApplication')
            ->assertHasErrors('formWardId');

        Livewire::actingAs($this->coordinator)->test(VolunteersIndex::class)
            ->set('fullName', 'Sample New Volunteer')->set('phone', '08061230005')
            ->set('formLgaId', $this->lga('udu')->id)->set('formWardId', $this->ward('udu')->id)
            ->set('skills', ['phone'])
            ->call('addApplication')
            ->assertHasNoErrors();

        $this->assertSame(['phone'], Volunteer::query()->sole()->skills);
    }

    public function test_volunteers_without_permission_cannot_open_the_volunteer_list(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);

        $this->signIn($volunteer->user)->get(route('workspace.volunteers.index'))->assertForbidden();
    }

    public function test_suspend_from_the_detail_page_requires_a_reason(): void
    {
        $volunteer = $this->volunteers->approve($this->application(), $this->coordinator);

        Livewire::actingAs($this->coordinator)->test(VolunteerShow::class, ['volunteer' => $volunteer])
            ->call('decide', 'suspend')->assertHasErrors('reason')
            ->set('reason', 'Conduct review')->call('decide', 'suspend')->assertHasNoErrors();

        $this->assertSame(VolunteerStatus::Suspended, $volunteer->refresh()->status);
    }
}
