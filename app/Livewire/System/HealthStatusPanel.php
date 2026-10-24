<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\HealthStatus;
use App\Services\Health\CheckResult;
use App\Services\Health\HealthChecker;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Live readiness of platform dependencies. Lazy-loaded so a slow dependency
 * never blocks the page; polls every 30 seconds while visible.
 */
#[Lazy]
final class HealthStatusPanel extends Component
{
    /** @var list<array{name: string, status: string, latency_ms: float|null, detail: string|null}> */
    public array $checks = [];

    public string $overall = 'ok';

    public string $checkedAt = '';

    public function mount(HealthChecker $checker): void
    {
        $this->refresh($checker);
    }

    public function refresh(HealthChecker $checker): void
    {
        $results = $checker->run();

        $this->checks = array_map(fn (CheckResult $result): array => [
            'name' => $result->name,
            'status' => $result->status->value,
            'latency_ms' => $result->latencyMs,
            'detail' => $result->detail,
        ], $results);

        $this->overall = HealthChecker::overall($results)->value;
        $this->checkedAt = now()->toIso8601String();
    }

    public function placeholder(): View
    {
        return view('livewire.system.health-status-panel-placeholder');
    }

    public function render(): View
    {
        return view('livewire.system.health-status-panel', [
            'overallStatus' => HealthStatus::from($this->overall),
        ]);
    }
}
