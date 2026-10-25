<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Services\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    private function supervisors(mixed $result): void
    {
        $repository = Mockery::mock(MasterSupervisorRepository::class);
        $result instanceof \Throwable
            ? $repository->shouldReceive('all')->andThrow($result)
            : $repository->shouldReceive('all')->andReturn($result);

        $this->app->instance(MasterSupervisorRepository::class, $repository);
    }

    public function test_reports_every_dependency_when_healthy(): void
    {
        $this->supervisors([(object) ['status' => 'running']]);
        Cache::put(HealthChecker::SCHEDULER_HEARTBEAT, now()->getTimestamp(), 600);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(7, 'checks')
            ->assertJsonPath('checks.5.name', 'scheduler')
            ->assertJsonPath('checks.6.name', 'sms')
            ->assertJsonPath('checks.0.name', 'database')
            ->assertJsonPath('checks.1.name', 'postgis')
            ->assertJsonPath('checks.1.status', 'ok')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_stopped_scheduler_degrades_readiness(): void
    {
        $this->supervisors([(object) ['status' => 'running']]);
        Cache::put(HealthChecker::SCHEDULER_HEARTBEAT, now()->subMinutes(10)->getTimestamp(), 600);

        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.5.status', 'degraded');
    }

    public function test_is_degraded_but_available_when_no_queue_supervisor_runs(): void
    {
        $this->supervisors([]);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.4.status', 'degraded');
    }

    public function test_returns_503_when_a_dependency_fails(): void
    {
        $this->supervisors(new RuntimeException('Redis down'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('checks.4.status', 'failed');
    }

    public function test_does_not_expose_dependency_details(): void
    {
        $this->supervisors([(object) ['status' => 'running']]);

        $response = $this->getJson('/health');

        $this->assertArrayNotHasKey('detail', $response->json('checks.0'));
        $response->assertDontSee('PostGIS 3');
    }

    public function test_does_not_start_a_session(): void
    {
        $this->supervisors([(object) ['status' => 'running']]);

        $response = $this->get('/health');

        $this->assertNull($response->headers->getCookies()[0] ?? null, 'Health probes must not set cookies.');
    }
}
