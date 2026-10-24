<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Imports\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ValidateImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1700;

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
            $imports->validate($job);
        }
    }
}
