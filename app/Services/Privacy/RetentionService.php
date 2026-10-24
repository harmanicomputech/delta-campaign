<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Supporter;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

/** Anonymises supporter records past their retention date (unless under legal hold). */
final class RetentionService
{
    public function __construct(private readonly ErasureService $erasure, private readonly AuditLogger $audit) {}

    /** @return Builder<Supporter> */
    public function due(): Builder
    {
        return Supporter::query()->whereNull('erased_at')->where('legal_hold', false)->where('retention_until', '<', now()->toDateString());
    }

    public function apply(bool $dryRun = false): int
    {
        $count = 0;

        $this->due()->chunkById(500, function ($supporters) use ($dryRun, &$count): void {
            foreach ($supporters as $supporter) {
                $count++;
                if (! $dryRun) {
                    $this->audit->as('retention', fn () => $this->erasure->anonymise($supporter, null, 'retention period ended'));
                }
            }
        });

        $this->audit->as('retention', fn () => $this->audit->record($dryRun ? 'privacy.retention_previewed' : 'privacy.retention_applied', context: ['count' => $count]));

        return $count;
    }
}
