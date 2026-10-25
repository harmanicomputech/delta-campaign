<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\ConsentChannel;
use App\Enums\HealthStatus;
use App\Services\Messaging\ProviderHealth;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Readiness checks for the platform's runtime dependencies.
 *
 * Each check is isolated: one dependency failing never prevents the others
 * from being reported. Failures are logged with the exception class only.
 */
final class HealthChecker
{
    public function __construct(private readonly MasterSupervisorRepository $supervisors) {}

    /**
     * @return list<CheckResult>
     */
    public function run(): array
    {
        return [
            $this->check('database', function (): string {
                DB::select('select 1');

                return DB::connection()->getDriverTitle();
            }),
            $this->check('postgis', function (): string {
                $version = DB::scalar('select postgis_lib_version()');

                return 'PostGIS '.$version;
            }),
            $this->check('redis', function (): string {
                Redis::connection()->ping();

                return 'Redis reachable';
            }),
            $this->check('cache', function (): string {
                $key = 'health:'.Str::random(12);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';

                if (! $ok) {
                    throw new \RuntimeException('Cache read-back mismatch');
                }

                return 'Store: '.config('cache.default');
            }),
            $this->queueCheck(),
            $this->schedulerCheck(),
            $this->smsCheck(),
        ];
    }

    /** Key the scheduler refreshes every minute (routes/console.php). */
    public const string SCHEDULER_HEARTBEAT = 'health:scheduler-heartbeat';

    /**
     * Scheduled work (sending, publishing, retention) stops silently if the
     * scheduler dies, so a stale heartbeat degrades readiness.
     */
    private function schedulerCheck(): CheckResult
    {
        $started = hrtime(true);
        $beat = Cache::get(self::SCHEDULER_HEARTBEAT);
        $age = is_int($beat) ? now()->getTimestamp() - $beat : null;

        return match (true) {
            $age === null => new CheckResult('scheduler', HealthStatus::Degraded, $this->elapsed($started), 'No scheduler heartbeat yet'),
            $age > 180 => new CheckResult('scheduler', HealthStatus::Degraded, $this->elapsed($started), 'Last heartbeat '.$age.'s ago'),
            default => new CheckResult('scheduler', HealthStatus::Ok, $this->elapsed($started), 'Heartbeat '.$age.'s ago'),
        };
    }

    /** An open SMS circuit breaker means messages are being held, not lost. */
    private function smsCheck(): CheckResult
    {
        $started = hrtime(true);

        try {
            $tripped = app(ProviderHealth::class)->trippedFor(ConsentChannel::Sms);
        } catch (Throwable $exception) {
            return new CheckResult('sms', HealthStatus::Degraded, $this->elapsed($started), class_basename($exception));
        }

        return $tripped > 0
            ? new CheckResult('sms', HealthStatus::Degraded, $this->elapsed($started), 'Provider paused for '.$tripped.'s after failures')
            : new CheckResult('sms', HealthStatus::Ok, $this->elapsed($started), 'Driver: '.config('campaign.sms.driver'));
    }

    /**
     * @param  list<CheckResult>  $results
     */
    public static function overall(array $results): HealthStatus
    {
        return HealthStatus::worst(array_map(fn (CheckResult $result): HealthStatus => $result->status, $results));
    }

    /**
     * @param  Closure(): string  $probe
     */
    private function check(string $name, Closure $probe): CheckResult
    {
        $started = hrtime(true);

        try {
            $detail = $probe();

            return new CheckResult($name, HealthStatus::Ok, $this->elapsed($started), $detail);
        } catch (Throwable $exception) {
            Log::warning('Health check failed', ['check' => $name, 'exception' => $exception::class]);

            return new CheckResult($name, HealthStatus::Failed, $this->elapsed($started), class_basename($exception));
        }
    }

    /**
     * Queue workers are supervised by Horizon. No running supervisor means jobs
     * will wait: the platform is degraded, not down.
     */
    private function queueCheck(): CheckResult
    {
        $started = hrtime(true);

        try {
            $masters = $this->supervisors->all();
        } catch (Throwable $exception) {
            Log::warning('Health check failed', ['check' => 'queue', 'exception' => $exception::class]);

            return new CheckResult('queue', HealthStatus::Failed, $this->elapsed($started), class_basename($exception));
        }

        if ($masters === []) {
            return new CheckResult('queue', HealthStatus::Degraded, $this->elapsed($started), 'No Horizon supervisor running');
        }

        $paused = collect($masters)->contains(fn (object $master): bool => ($master->status ?? null) === 'paused');

        return new CheckResult(
            'queue',
            $paused ? HealthStatus::Degraded : HealthStatus::Ok,
            $this->elapsed($started),
            $paused ? 'Horizon paused' : trans_choice('health.supervisors', count($masters), ['count' => count($masters)]),
        );
    }

    private function elapsed(int $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 1);
    }
}
