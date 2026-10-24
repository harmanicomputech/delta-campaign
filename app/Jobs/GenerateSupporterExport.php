<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\Exports\SupporterExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateSupporterExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1700;

    public function __construct(public readonly string $exportId)
    {
        // Long-running: use the connection whose retry_after exceeds the job timeout.
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }
        $this->onQueue('exports');
    }

    public function handle(SupporterExportService $exports): void
    {
        $export = ExportJob::query()->find($this->exportId);

        if ($export !== null) {
            $exports->generate($export);
        }
    }
}
