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
        $this->onConnection('redis-long')->onQueue('imports');
    }

    public function handle(ImportService $imports): void
    {
        $job = ImportJob::query()->find($this->importJobId);

        if ($job !== null) {
            $imports->validate($job);
        }
    }
}
