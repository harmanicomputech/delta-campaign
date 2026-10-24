<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class FieldNavigationTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_volunteers_see_their_field_tools_but_not_management_lists(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $this->signIn($volunteer)->get(route('workspace.tasks.index'))->assertOk()
            ->assertSee(route('workspace.canvassing.index'), false)
            ->assertSee(route('workspace.training.index'), false)
            ->assertDontSee(route('workspace.volunteers.index'), false)
            ->assertSee(__('tasks.my_open'));
    }

    public function test_detail_pages_keep_their_section_highlighted(): void
    {
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $this->signIn($coordinator)->get(route('workspace.volunteers.index'))->assertOk()->assertSee('aria-current="page"', false);
    }

    public function test_people_without_field_permissions_are_refused(): void
    {
        $analyst = User::factory()->withRole('analyst')->create();

        $this->signIn($analyst)->get(route('workspace.tasks.index'))->assertForbidden();
        $this->signIn($analyst)->get(route('workspace.canvassing.index'))->assertForbidden();
    }
}
