<?php

declare(strict_types=1);

namespace Tests\Feature\Geography;

use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Jobs\ApplyImport;
use App\Jobs\ValidateImport;
use App\Models\ImportJob;
use App\Models\PollingUnit;
use App\Models\Ward;
use App\Services\Imports\ImportInProgress;
use App\Services\Imports\ImportService;
use Database\Seeders\GeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

final class PollingUnitImportTest extends TestCase
{
    use RefreshDatabase;

    private const string HEADER = 'lga,ward_code,ward_name,pu_code,pu_name,latitude,longitude,registered_voters';

    private ImportService $imports;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(GeographySeeder::class);
        $this->imports = app(ImportService::class);
    }

    /**
     * @param  list<string>  $lines
     */
    private function csv(array $lines, string $header = self::HEADER): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $header."\n".implode("\n", $lines)."\n");

        return $path;
    }

    /**
     * @param  list<string>  $lines
     */
    private function validated(array $lines, string $header = self::HEADER, bool $sample = true): ImportJob
    {
        $job = $this->imports->start($this->csv($lines, $header), 'units.csv', ImportType::PollingUnits, null, $sample, queue: false);
        $this->imports->validate($job->refresh());

        return $job->refresh();
    }

    private function applied(ImportJob $job): ImportJob
    {
        $this->imports->confirm($job, null, queue: false);
        $this->imports->apply($job->refresh());

        return $job->refresh();
    }

    /** @return list<string> */
    private function sampleLines(): array
    {
        return [
            'Oshimili South,SMP-W01,Sample Ward One,SMP-PU-001,Sample Unit A,6.20,6.70,500',
            'Oshimili South,SMP-W01,Sample Ward One,SMP-PU-002,Sample Unit B,6.21,6.71,',
            'oshimili-south,SMP-W02,Sample Ward Two,SMP-PU-003,Sample Unit C,,,',
            'Warri South,SMP-W01,Sample Ward One,SMP-PU-004,Sample Unit D,5.52,5.75,1200',
        ];
    }

    public function test_starting_an_import_stores_the_file_and_queues_validation(): void
    {
        Bus::fake();

        $job = $this->imports->start($this->csv($this->sampleLines()), '../../etc/units.csv', ImportType::PollingUnits, null);

        Bus::assertDispatched(ValidateImport::class, fn ($dispatched) => $dispatched->importJobId === $job->id);
        Storage::disk('local')->assertExists($job->path);
        $this->assertSame('units.csv', $job->original_name, 'Paths must be stripped from uploaded names.');
        $this->assertSame(ImportStatus::Uploaded, $job->status);
        $this->assertSame(64, strlen($job->file_hash));
    }

    public function test_valid_file_is_previewed_then_applied(): void
    {
        $job = $this->validated($this->sampleLines());

        $this->assertSame(ImportStatus::Validated, $job->status);
        $this->assertSame([4, 4, 0], [$job->total_rows, $job->valid_rows, $job->invalid_rows]);
        $this->assertSame(0, PollingUnit::query()->count(), 'Nothing is written before confirmation.');

        $job = $this->applied($job);

        $this->assertSame(ImportStatus::Completed, $job->status);
        $this->assertSame([4, 0, 0], [$job->created_count, $job->updated_count, $job->unchanged_count]);
        $this->assertSame(3, Ward::query()->count());
        $unit = PollingUnit::query()->where('code', 'SMP-PU-001')->firstOrFail();
        $this->assertSame(500, $unit->registered_voters);
        $this->assertTrue($unit->is_sample);
        $this->assertSame($job->id, $unit->import_job_id);
    }

    public function test_reimporting_the_same_file_changes_nothing(): void
    {
        $this->applied($this->validated($this->sampleLines()));
        $again = $this->validated($this->sampleLines());

        $this->assertNotNull($again->previousIdenticalImport());
        $this->assertSame(4, $again->rows()->where('action', ImportRowAction::Unchanged)->count());

        $again = $this->applied($again);
        $this->assertSame([0, 0, 4], [$again->created_count, $again->updated_count, $again->unchanged_count]);
        $this->assertSame(4, PollingUnit::query()->count());
    }

    public function test_changed_rows_are_updated_and_blank_values_never_erase_data(): void
    {
        $this->applied($this->validated($this->sampleLines()));

        $job = $this->applied($this->validated([
            'Oshimili South,SMP-W01,Sample Ward One,SMP-PU-001,Sample Unit A (renamed),,,',
            'Oshimili South,SMP-W01,Sample Ward One,SMP-PU-002,Sample Unit B,6.21,6.71,',
        ]));

        $this->assertSame([0, 1, 1], [$job->created_count, $job->updated_count, $job->unchanged_count]);
        $unit = PollingUnit::query()->where('code', 'SMP-PU-001')->firstOrFail();
        $this->assertSame('Sample Unit A (renamed)', $unit->name);
        $this->assertSame('6.200000', $unit->latitude);
        $this->assertSame(500, $unit->registered_voters);
    }

    public function test_units_missing_from_a_later_file_are_never_deleted(): void
    {
        $this->applied($this->validated($this->sampleLines()));
        $this->applied($this->validated([$this->sampleLines()[0]]));

        $this->assertSame(4, PollingUnit::query()->count());
    }

    public function test_row_level_errors_are_reported(): void
    {
        $job = $this->validated([
            'Atlantis,W1,Ward,P-1,Unit,,,',
            'Sapele,,Ward,P-2,Unit,,,',
            'Sapele,W1,Ward,P-3,Unit,5.9,,',
            'Sapele,W1,Ward,P-4,Unit,abc,def,',
            'Sapele,W1,Ward,P-5,Unit,9.0,3.0,',
            'Sapele,W1,Ward,P-6,Unit,5.75,5.52,',
            'Sapele,W1,Ward,P-7,Unit,,,lots',
            'Sapele,W1,Ward,P 8,Unit,,,',
            'Sapele,W1,Ward,P-9,Unit,,,',
            'Sapele,W1,Ward,p-9,Unit again,,,',
        ]);

        $errors = $job->rows()->orderBy('row_number')->pluck('errors', 'row_number');

        $this->assertStringContainsString('Unknown LGA "Atlantis"', $errors[1][0]);
        $this->assertStringContainsString('Ward code is required', implode(' ', $errors[2]));
        $this->assertStringContainsString('given together', $errors[3][0]);
        $this->assertStringContainsString('decimal numbers', $errors[4][0]);
        $this->assertStringContainsString('outside Delta State', $errors[5][0]);
        $this->assertNull($errors[6], 'Swapped-looking coordinates inside the box are accepted.');
        $this->assertStringContainsString('whole number', $errors[7][0]);
        $this->assertStringContainsString('may contain only', $errors[8][0]);
        $this->assertNull($errors[9]);
        $this->assertStringContainsString('Duplicate PU code P-9: already on row 9', $errors[10][0]);
        $this->assertSame(8, $job->invalid_rows);
    }

    public function test_conflicting_ward_names_within_a_file_are_rejected(): void
    {
        $job = $this->validated([
            'Udu,W1,Ward Alpha,P-1,Unit 1,,,',
            'Udu,W1,Ward Beta,P-2,Unit 2,,,',
        ]);

        $this->assertStringContainsString('named "Ward Alpha" on an earlier row', implode(' ', $job->rows()->where('row_number', 2)->first()->errors));
    }

    public function test_warnings_do_not_block_rows(): void
    {
        $this->applied($this->validated(['Udu,W1,Ward Alpha,P-1,Unit 1,,,']));

        $job = $this->validated([
            'Udu,W1,Ward Alpha Renamed,P-1,Unit 1,,,',
            'Udu,W1,Ward Alpha Renamed,P-2,Same Name,,,',
            'Udu,W1,Ward Alpha Renamed,P-3,Same Name,,,',
        ]);

        $rows = $job->rows()->orderBy('row_number')->get();
        $this->assertSame(3, $job->valid_rows);
        $this->assertStringContainsString('will be renamed', $rows[0]->warnings[0]);
        $this->assertStringContainsString('Possible duplicate', implode(' ', $rows[2]->warnings));

        $this->applied($job);
        $this->assertSame('Ward Alpha Renamed', Ward::query()->where('code', 'W1')->value('name'));
    }

    public function test_moving_a_unit_to_another_ward_is_refused(): void
    {
        $this->applied($this->validated(['Udu,W1,Ward Alpha,P-1,Unit 1,,,']));

        $job = $this->validated(['Udu,W2,Ward Beta,P-1,Unit 1,,,']);

        $this->assertStringContainsString('different ward', implode(' ', $job->rows()->first()->errors));
    }

    public function test_only_valid_rows_are_applied_from_a_mixed_file(): void
    {
        $job = $this->applied($this->validated(['Udu,W1,Ward,P-1,Unit,,,', 'Nowhere,W1,Ward,P-2,Unit,,,']));

        $this->assertSame(1, $job->created_count);
        $this->assertSame(['P-1'], PollingUnit::query()->pluck('code')->all());
    }

    public function test_rows_invalidated_by_changes_after_preview_are_skipped(): void
    {
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,']);
        $other = Ward::factory()->create(['code' => 'OTHER']);
        PollingUnit::factory()->create(['code' => 'P-1', 'ward_id' => $other->id]);

        $job = $this->applied($job);

        $this->assertSame(ImportRowStatus::Skipped, $job->rows()->first()->status);
        $this->assertSame(0, $job->created_count);
    }

    public function test_missing_columns_fail_validation(): void
    {
        $job = $this->validated(['Udu,W1'], 'lga,ward_code');

        $this->assertSame(ImportStatus::Failed, $job->status);
        $this->assertStringContainsString('ward_name, pu_code, pu_name', $job->failure_reason);
    }

    public function test_headers_are_normalised_and_bom_is_ignored(): void
    {
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit'], "\xEF\xBB\xBFLGA, Ward Code ,Ward-Name,PU Code,PU name");

        $this->assertSame(ImportStatus::Validated, $job->status);
        $this->assertSame(1, $job->valid_rows);
    }

    public function test_empty_and_oversized_files_fail(): void
    {
        $this->assertSame('The file contains no data rows.', $this->validated([])->failure_reason);

        config(['campaign.imports.max_rows' => 2]);
        $this->assertStringContainsString('more than 2 rows', $this->validated($this->sampleLines())->failure_reason);
    }

    public function test_error_report_lists_problem_rows_and_neutralises_formulas(): void
    {
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,', 'Nowhere,W1,=HYPERLINK("x"),P-2,Unit,,,']);

        $report = Storage::disk('local')->get($job->error_report_path);
        $this->assertStringContainsString('Unknown LGA', $report);
        $this->assertStringContainsString("'=HYPERLINK", $report);
        $this->assertStringNotContainsString('P-1', $report);
    }

    public function test_confirm_and_cancel_guard_their_states(): void
    {
        $invalid = $this->validated(['Nowhere,W1,Ward,P-1,Unit,,,']);
        $this->assertThrows(fn () => $this->imports->confirm($invalid, null, queue: false), LogicException::class, 'no valid rows');

        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,']);
        $this->imports->cancel($job);
        $this->assertSame(ImportStatus::Cancelled, $job->refresh()->status);
        $this->assertThrows(fn () => $this->imports->confirm($job, null, queue: false), LogicException::class);
    }

    public function test_confirming_queues_the_apply_job(): void
    {
        Bus::fake();
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,']);

        $this->imports->confirm($job, null);

        Bus::assertDispatched(ApplyImport::class);
        $this->assertSame(ImportStatus::Importing, $job->refresh()->status);
    }

    public function test_only_one_import_applies_at_a_time(): void
    {
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,']);
        $this->imports->confirm($job, null, queue: false);
        $lock = Cache::lock('imports:apply:polling_units', 60);
        $lock->get();

        try {
            $this->assertThrows(fn () => $this->imports->apply($job->refresh()), ImportInProgress::class);
            $this->assertSame(0, PollingUnit::query()->count());
        } finally {
            $lock->release();
        }
    }

    public function test_validation_and_apply_are_idempotent_on_retry(): void
    {
        $job = $this->validated(['Udu,W1,Ward,P-1,Unit,,,']);
        $this->imports->validate($job);
        $this->assertSame(1, $job->rows()->count());

        $job = $this->applied($job);
        $this->imports->apply($job);
        $this->assertSame(1, PollingUnit::query()->count());
    }

    public function test_cli_validates_and_applies(): void
    {
        $path = $this->csv($this->sampleLines());

        $this->artisan('geography:import', ['file' => $path])
            ->expectsOutputToContain('Validated only')
            ->assertSuccessful();
        $this->assertSame(0, PollingUnit::query()->count());

        $this->artisan('geography:import', ['file' => $path, '--confirm' => true, '--sample' => true])
            ->expectsOutputToContain('Import complete')
            ->assertSuccessful();
        $this->assertSame(4, PollingUnit::query()->where('is_sample', true)->count());

        $this->artisan('geography:import', ['file' => '/nope.csv'])->assertFailed();
    }
}
