<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Imports\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ApplyImport implements ShouldQueue
{
    use Queueable;

    /** Retried while another import holds the apply lock. */
    public int $tries = 10;

    public int $timeout = 1700;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $importJobId)
    {
        // Long-running: use the connection whose retry_after exceeds the job timeout.
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }
        $this->onQueue('imports');
    }

    public function handle(ImportService $imports): void
    {
        $job = ImportJob::query()->find($this->importJobId);

        if ($job !== null) {
            $imports->apply($job);
        }
    }
}
