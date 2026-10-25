<?php

declare(strict_types=1);

namespace Tests\Feature\Ussd;

use App\Livewire\Workspace\Ussd\UssdSimulator;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSupporters;
use Tests\TestCase;

final class SimulatorTest extends TestCase
{
    use InteractsWithSupporters, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSupporterContext();
    }

    private function director(): User
    {
        return User::factory()->withRole('state_director')->withTwoFactor()->create();
    }

    public function test_the_simulator_runs_the_real_menu_and_practice_mode_saves_nothing(): void
    {
        $page = Livewire::actingAs($this->director())->test(UssdSimulator::class)
            ->call('dial')->assertSee('Welcome to')
            ->set('input', '1')->call('send')->assertSee('Main menu')
            ->set('input', '1')->call('send')
            ->set('input', 'Sim Person')->call('send')
            ->set('input', '1')->call('send')
            ->set('input', '1')->call('send')
            ->set('input', '1')->call('send')
            ->assertSee('You are registered')
            ->assertSet('ended', true);

        $this->assertSame(0, Supporter::query()->count(), 'Practice mode rolls back');
        $this->assertSame([], OutboxSmsGateway::messages());
        $this->assertCount(7, $page->get('log'));
    }

    public function test_switching_practice_off_saves_sample_records_outside_production(): void
    {
        Livewire::actingAs($this->director())->test(UssdSimulator::class)
            ->set('practice', false)
            ->call('dial')
            ->set('input', '1')->call('send')
            ->set('input', '1')->call('send')
            ->set('input', 'Sim Person')->call('send')
            ->set('input', '1')->call('send')
            ->set('input', '2')->call('send')
            ->set('input', '1')->call('send');

        $this->assertTrue(Supporter::query()->sole()->is_sample);
    }

    public function test_the_simulator_needs_its_permission(): void
    {
        $this->signIn(User::factory()->withRole('volunteer', $this->ward('udu')->id)->create())->get(route('workspace.ussd-simulator'))->assertForbidden();
        $this->signIn($this->director())->get(route('workspace.ussd-simulator'))->assertOk()->assertSee(__('simulator.title'));
    }
}
