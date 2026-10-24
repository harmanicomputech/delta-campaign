<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\HealthStatus;
use PHPUnit\Framework\TestCase;

final class HealthStatusTest extends TestCase
{
    public function test_worst_status_wins(): void
    {
        $this->assertSame(HealthStatus::Ok, HealthStatus::worst([HealthStatus::Ok, HealthStatus::Ok]));
        $this->assertSame(HealthStatus::Degraded, HealthStatus::worst([HealthStatus::Ok, HealthStatus::Degraded]));
        $this->assertSame(HealthStatus::Failed, HealthStatus::worst([HealthStatus::Degraded, HealthStatus::Failed, HealthStatus::Ok]));
        $this->assertSame(HealthStatus::Ok, HealthStatus::worst([]));
    }
}
