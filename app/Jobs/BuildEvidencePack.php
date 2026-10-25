<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EvidencePack;
use App\Services\Election\EvidencePackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class BuildEvidencePack implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1700;

    public function __construct(public readonly int $packId)
    {
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }
        $this->onQueue('exports');
    }

    public function handle(EvidencePackService $packs): void
    {
        $pack = EvidencePack::query()->find($this->packId);

        if ($pack !== null && $pack->status === 'queued') {
            $packs->build($pack);
        }
    }
}
