<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EvidenceFile;
use App\Services\Election\EvidencePackService;
use App\Services\Election\EvidenceStore;
use Illuminate\Console\Command;

/** Re-hashes stored evidence and reports any file that no longer matches its recorded SHA-256. */
final class VerifyEvidence extends Command
{
    protected $signature = 'evidence:verify {--since= : Only files uploaded after this date}';

    protected $description = 'Check every evidence file against its recorded SHA-256 and expire old evidence packs';

    public function handle(EvidenceStore $store, EvidencePackService $packs): int
    {
        $checked = $failed = 0;
        EvidenceFile::query()->when($this->option('since'), fn ($q, $since) => $q->where('created_at', '>=', $since))
            ->lazyById(500)->each(function (EvidenceFile $file) use ($store, &$checked, &$failed): void {
                $checked++;
                if (! $store->verify($file)) {
                    $failed++;
                    $this->error("Integrity check failed: evidence {$file->uuid}");
                }
            });

        $expired = $packs->prune();
        $this->info("Checked {$checked} evidence file(s): {$failed} failed. Expired {$expired} evidence pack(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
