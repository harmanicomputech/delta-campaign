<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\HealthStatus;

final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public ?float $latencyMs = null,
        public ?string $detail = null,
    ) {}

    /**
     * @return array{name: string, status: string, latency_ms: float|null}
     */
    public function toPublicArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
