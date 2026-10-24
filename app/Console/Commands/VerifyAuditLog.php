<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('audit:verify {--from= : Start at this entry id}')]
#[Description('Verify the audit-log hash chain')]
final class VerifyAuditLog extends Command
{
    public function handle(AuditChainVerifier $verifier, AuditLogger $audit): int
    {
        $result = $verifier->verify($this->option('from') ? (int) $this->option('from') : null);

        $audit->as('audit-verifier', fn () => $audit->record('audit.verified', context: [
            'valid' => $result['valid'], 'checked' => $result['checked'], 'broken_id' => $result['broken_id'],
        ]));

        if (! $result['valid']) {
            Log::critical('Audit chain verification failed', $result);
            $this->error("Chain broken at entry #{$result['broken_id']}: {$result['reason']}");

            return self::FAILURE;
        }

        $this->info("Audit chain valid: {$result['checked']} entries, last #{$result['last_id']}.");

        return self::SUCCESS;
    }
}
