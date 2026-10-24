<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HealthStatus;
use App\Services\Health\CheckResult;
use App\Services\Health\HealthChecker;
use Illuminate\Http\JsonResponse;

/**
 * Readiness probe for load balancers and uptime monitors.
 * Returns 503 when any dependency has failed. Detail strings are not exposed.
 */
final class HealthController extends Controller
{
    public function __invoke(HealthChecker $checker): JsonResponse
    {
        $results = $checker->run();
        $overall = HealthChecker::overall($results);

        return response()->json([
            'status' => $overall->value,
            'checks' => array_map(fn (CheckResult $result): array => $result->toPublicArray(), $results),
            'checked_at' => now()->toIso8601String(),
        ], $overall === HealthStatus::Failed ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
