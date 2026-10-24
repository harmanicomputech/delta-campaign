<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Exports\SupporterExportService;
use App\Services\Privacy\RetentionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('privacy:apply-retention {--dry-run : Count records due without changing them}')]
#[Description('Anonymise supporter records past their retention date and delete expired exports')]
final class ApplyRetention extends Command
{
    public function handle(RetentionService $retention, SupporterExportService $exports): int
    {
        $count = $retention->apply((bool) $this->option('dry-run'));
        $this->info(($this->option('dry-run') ? 'Due for anonymisation: ' : 'Anonymised: ').$count);

        if (! $this->option('dry-run')) {
            $this->info('Expired exports deleted: '.$exports->prune());
        }

        return self::SUCCESS;
    }
}
