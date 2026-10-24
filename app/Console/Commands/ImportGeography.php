<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\ImportJob;
use App\Services\Imports\ImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geography:import {file : CSV with lga, ward_code, ward_name, pu_code, pu_name[, latitude, longitude, registered_voters]}
    {--confirm : Apply the import after a successful validation}
    {--sample : Flag created wards and polling units as SAMPLE data}')]
#[Description('Validate (and optionally apply) a ward/polling-unit reference-data CSV')]
final class ImportGeography extends Command
{
    public function handle(ImportService $imports): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        // Run the stages inline (no queue) so the operator sees the result immediately.
        $job = $imports->start($file, $file, ImportType::PollingUnits, null, (bool) $this->option('sample'), queue: false);
        $imports->validate($job->refresh());
        $job->refresh();

        if ($job->status === ImportStatus::Failed) {
            $this->error($job->failure_reason ?? 'Validation failed.');

            return self::FAILURE;
        }

        $this->summary($job);

        if (! $this->option('confirm')) {
            $this->info("Validated only. Re-run with --confirm to apply (import {$job->id}).");

            return self::SUCCESS;
        }

        if ($job->valid_rows === 0) {
            $this->error('No valid rows to import.');

            return self::FAILURE;
        }

        $imports->confirm($job, null, queue: false);
        $imports->apply($job->refresh());
        $job->refresh();

        if ($job->status !== ImportStatus::Completed) {
            $this->error($job->failure_reason ?? 'Import failed.');

            return self::FAILURE;
        }

        $this->table(['Created', 'Updated', 'Unchanged'], [[$job->created_count, $job->updated_count, $job->unchanged_count]]);
        $this->info('Import complete.');

        return self::SUCCESS;
    }

    private function summary(ImportJob $job): void
    {
        $this->table(['Rows', 'Valid', 'Invalid', 'With warnings'], [[$job->total_rows, $job->valid_rows, $job->invalid_rows, $job->warning_rows]]);

        if ($previous = $job->previousIdenticalImport()) {
            $this->warn("An identical file was already imported on {$previous->completed_at}. Re-importing will leave records unchanged.");
        }

        $job->rows()->where('status', ImportRowStatus::Invalid)->orderBy('row_number')->limit(20)->get()
            ->each(fn ($row) => $this->line("  Row {$row->row_number}: ".implode(' ', $row->errors ?? [])));

        if ($job->invalid_rows > 20) {
            $this->line('  … see the error report: '.$job->error_report_path);
        }
    }
}
