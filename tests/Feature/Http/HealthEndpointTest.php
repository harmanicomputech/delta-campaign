<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

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

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(5, 'checks')
            ->assertJsonPath('checks.0.name', 'database')
            ->assertJsonPath('checks.1.name', 'postgis')
            ->assertJsonPath('checks.1.status', 'ok')
            ->assertHeader('Cache-Control', 'no-store, private');
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
