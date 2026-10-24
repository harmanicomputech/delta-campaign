<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\System\HealthStatusPanel;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class HealthStatusPanelTest extends TestCase
{
    public function test_shows_live_dependency_status(): void
    {
        $repository = Mockery::mock(MasterSupervisorRepository::class);
        $repository->shouldReceive('all')->andReturn([]);
        $this->app->instance(MasterSupervisorRepository::class, $repository);

        Livewire::withoutLazyLoading()
            ->test(HealthStatusPanel::class)
            ->assertSet('overall', 'degraded')
            ->assertSee(__('health.checks.postgis'))
            ->assertSee('No Horizon supervisor running')
            ->assertSee(__('health.overall.degraded'))
            ->call('refresh')
            ->assertSet('overall', 'degraded');
    }
}
