<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes the latest audit hash to the application log stream, which is shipped
 * off the database host. A later chain rewrite (e.g. by a database superuser who
 * disables the triggers) would not match these external checkpoints.
 */
#[Signature('audit:checkpoint')]
#[Description('Record the latest audit-log hash in the external log stream')]
final class CheckpointAuditLog extends Command
{
    public function handle(): int
    {
        $latest = DB::table('audit_logs')->orderByDesc('id')->first(['id', 'hash', 'occurred_at']);

        Log::channel(config('logging.default'))->notice('audit.checkpoint', [
            'audit_id' => $latest?->id,
            'audit_hash' => $latest?->hash,
            'audit_occurred_at' => $latest?->occurred_at,
        ]);

        $this->info($latest ? "Checkpoint #{$latest->id} {$latest->hash}" : 'Audit log is empty.');

        return self::SUCCESS;
    }
}
