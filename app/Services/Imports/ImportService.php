<?php

declare(strict_types=1);

namespace App\Services\Imports;

use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Jobs\ApplyImport;
use App\Jobs\ValidateImport;
use App\Models\ImportJob;
use App\Models\ImportRow;
use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Ward;
use App\Services\Geography\PollingUnitRowValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Staged reference-data imports: start → validate → (preview) → confirm → apply.
 *
 * Re-importing the same data is safe: unchanged rows are detected and left
 * alone, and polling units missing from a file are never deleted.
 */
final class ImportService
{
    private const string LOCK = 'imports:apply:polling_units';

    /**
     * @param  bool  $queue  false runs no background job (the caller drives validation, e.g. the CLI)
     */
    public function start(string $sourcePath, string $originalName, ImportType $type, ?User $user, bool $sample = false, bool $queue = true): ImportJob
    {
        $disk = (string) config('campaign.imports.disk');
        $id = (string) Str::uuid();
        $path = "imports/{$id}.csv";

        $stream = fopen($sourcePath, 'r');
        Storage::disk($disk)->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $job = new ImportJob;
        $job->forceFill([
            'id' => $id,
            'type' => $type,
            'status' => ImportStatus::Uploaded,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'file_hash' => (string) hash_file('sha256', $sourcePath),
            'is_sample' => $sample,
            'created_by' => $user?->getKey(),
        ])->save();

        if ($queue) {
            ValidateImport::dispatch($job->id);
        }

        return $job;
    }

    public function validate(ImportJob $job): void
    {
        if ($job->status !== ImportStatus::Uploaded) {
            return; // Idempotent: a retried job does not validate twice.
        }

        $job->update(['status' => ImportStatus::Validating]);
        $job->rows()->delete();

        try {
            $reader = new CsvReader(Storage::disk($job->disk)->path($job->path));
            $missing = PollingUnitRowValidator::missingColumns($reader->header());

            if ($missing !== []) {
                $this->fail($job, 'Missing required columns: '.implode(', ', $missing).'. Required: '.implode(', ', PollingUnitRowValidator::REQUIRED_COLUMNS).'.');

                return;
            }

            $validator = new PollingUnitRowValidator(config('campaign.geography.bounds'));
            $counts = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'warnings' => 0];
            $batch = [];
            $max = (int) config('campaign.imports.max_rows');

            foreach ($reader->rows() as $number => $row) {
                if ($number > $max) {
                    $this->fail($job, "The file has more than {$max} rows. Split it into smaller files.");

                    return;
                }

                $result = $validator->validate($number, $row);
                $valid = $result['errors'] === [];

                $counts['total']++;
                $counts[$valid ? 'valid' : 'invalid']++;
                $counts['warnings'] += $result['warnings'] === [] ? 0 : 1;

                $batch[] = [
                    'import_job_id' => $job->id,
                    'row_number' => $number,
                    'payload' => json_encode(array_intersect_key($row, array_flip([...PollingUnitRowValidator::REQUIRED_COLUMNS, ...PollingUnitRowValidator::OPTIONAL_COLUMNS]))),
                    'status' => ($valid ? ImportRowStatus::Valid : ImportRowStatus::Invalid)->value,
                    'action' => $result['action']?->value,
                    'errors' => $valid ? null : json_encode($result['errors']),
                    'warnings' => $result['warnings'] === [] ? null : json_encode($result['warnings']),
                ];

                if (count($batch) >= (int) config('campaign.imports.chunk')) {
                    ImportRow::query()->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                ImportRow::query()->insert($batch);
            }

            if ($counts['total'] === 0) {
                $this->fail($job, 'The file contains no data rows.');

                return;
            }

            $job->update([
                'status' => ImportStatus::Validated,
                'total_rows' => $counts['total'],
                'valid_rows' => $counts['valid'],
                'invalid_rows' => $counts['invalid'],
                'warning_rows' => $counts['warnings'],
                'error_report_path' => $this->writeReport($job),
                'validated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($job, 'The file could not be read as CSV.');
        }
    }

    public function confirm(ImportJob $job, ?User $user, bool $queue = true): void
    {
        if (! $job->status->canConfirm()) {
            throw new LogicException('Only validated imports can be confirmed.');
        }

        if ($job->valid_rows === 0) {
            throw new LogicException('There are no valid rows to import.');
        }

        $job->update([
            'status' => ImportStatus::Importing,
            'confirmed_by' => $user?->getKey(),
            'confirmed_at' => now(),
        ]);

        if ($queue) {
            ApplyImport::dispatch($job->id);
        }
    }

    public function cancel(ImportJob $job): void
    {
        if (! in_array($job->status, [ImportStatus::Uploaded, ImportStatus::Validated], true)) {
            throw new LogicException('Only imports that have not started applying can be cancelled.');
        }

        $job->update(['status' => ImportStatus::Cancelled]);
    }

    public function apply(ImportJob $job): void
    {
        if ($job->status !== ImportStatus::Importing) {
            return;
        }

        $lock = Cache::lock(self::LOCK, 1800);

        if (! $lock->get()) {
            // Another import is applying; the job is retried by the queue.
            throw new ImportInProgress('Another geography import is being applied.');
        }

        try {
            // Re-validate against current data: it may have changed since the preview.
            $validator = new PollingUnitRowValidator(config('campaign.geography.bounds'));
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            // chunkById, not chunk: rows leave the "valid" filter as they are applied.
            $job->rows()->where('status', ImportRowStatus::Valid)
                ->chunkById((int) config('campaign.imports.chunk'), function ($rows) use ($job, $validator, &$counts): void {
                    DB::transaction(function () use ($rows, $job, $validator, &$counts): void {
                        foreach ($rows as $row) {
                            $result = $validator->validate($row->row_number, $row->payload);

                            if ($result['errors'] !== []) {
                                $row->update(['status' => ImportRowStatus::Skipped, 'errors' => $result['errors']]);

                                continue;
                            }

                            $action = $this->applyRow($job, $result['normalised'], $result['action']);
                            $counts[$action->value === 'create' ? 'created' : ($action->value === 'update' ? 'updated' : 'unchanged')]++;
                            $row->update(['status' => ImportRowStatus::Applied, 'action' => $action]);
                        }
                    });
                });

            $job->update([
                'status' => ImportStatus::Completed,
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'unchanged_count' => $counts['unchanged'],
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($job, 'The import stopped part-way. Rows already applied are kept; re-importing the same file is safe.');
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyRow(ImportJob $job, array $data, ?ImportRowAction $planned): ImportRowAction
    {
        $ward = Ward::query()->firstOrNew(['lga_id' => $data['lga_id'], 'code' => $data['ward_code']]);
        $ward->name = $data['ward_name'];
        $ward->is_sample = $ward->exists ? $ward->is_sample : $job->is_sample;
        $ward->save();

        $unit = PollingUnit::query()->firstOrNew(['code' => $data['pu_code']]);
        $unit->fill(array_filter([
            'ward_id' => $ward->id,
            'name' => $data['pu_name'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'registered_voters' => $data['registered_voters'],
        ], fn ($value): bool => $value !== null));

        if (! $unit->exists) {
            $unit->is_sample = $job->is_sample;
            $unit->import_job_id = $job->id;
        }

        $dirty = $unit->isDirty();
        $unit->save();

        if (! $unit->wasRecentlyCreated && ! $dirty && $planned === ImportRowAction::Update) {
            return ImportRowAction::Update; // Only the ward name changed.
        }

        return $unit->wasRecentlyCreated ? ImportRowAction::Create : ($dirty ? ImportRowAction::Update : ImportRowAction::Unchanged);
    }

    private function writeReport(ImportJob $job): ?string
    {
        if ($job->rows()->where(fn ($q) => $q->where('status', ImportRowStatus::Invalid)->orWhereNotNull('warnings'))->doesntExist()) {
            return null;
        }

        $path = "imports/{$job->id}-report.csv";
        $handle = fopen('php://temp', 'w+');
        $columns = [...PollingUnitRowValidator::REQUIRED_COLUMNS, ...PollingUnitRowValidator::OPTIONAL_COLUMNS];
        fputcsv($handle, ['row', 'status', 'errors', 'warnings', ...$columns], escape: '');

        $job->rows()->where(fn ($q) => $q->where('status', ImportRowStatus::Invalid)->orWhereNotNull('warnings'))
            ->orderBy('row_number')
            ->each(function (ImportRow $row) use ($handle, $columns): void {
                fputcsv($handle, [
                    $row->row_number,
                    $row->status->value,
                    implode(' | ', $row->errors ?? []),
                    implode(' | ', $row->warnings ?? []),
                    ...array_map(fn (string $column): string => self::spreadsheetSafe((string) ($row->payload[$column] ?? '')), $columns),
                ], escape: '');
            });

        rewind($handle);
        Storage::disk($job->disk)->put($path, (string) stream_get_contents($handle));
        fclose($handle);

        return $path;
    }

    /** Neutralises spreadsheet formula injection in exported cells. */
    public static function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    private function fail(ImportJob $job, string $reason): void
    {
        $job->update(['status' => ImportStatus::Failed, 'failure_reason' => $reason]);
    }
}
