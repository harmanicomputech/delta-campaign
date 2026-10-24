<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\DesignSystem\SampleRecordsTable;
use Livewire\Livewire;
use Tests\TestCase;

final class SampleRecordsTableTest extends TestCase
{
    public function test_paginates_on_the_server(): void
    {
        Livewire::test(SampleRecordsTable::class)
            ->assertSee('Showing 1–10 of 137')
            ->call('nextPage')
            ->assertSee('Showing 11–20 of 137');
    }

    public function test_search_filters_and_resets_to_the_first_page(): void
    {
        Livewire::test(SampleRecordsTable::class)
            ->call('nextPage')
            ->set('search', 'Person 13')
            ->assertSee('Showing 1–8 of 8')
            ->assertSee('Sample Person 130')
            ->assertDontSee('Sample Person 012');
    }

    public function test_sorts_and_toggles_direction(): void
    {
        $component = Livewire::test(SampleRecordsTable::class)->call('sortBy', 'reference');
        $this->assertSame('SMP-0001', $component->viewData('records')->first()['reference']);

        $component->call('sortBy', 'reference');
        $this->assertSame('SMP-0137', $component->viewData('records')->first()['reference']);
    }

    public function test_ignores_unknown_sort_columns(): void
    {
        Livewire::test(SampleRecordsTable::class)
            ->call('sortBy', 'password')
            ->assertSet('sort', 'updated_at');
    }

    public function test_filters_by_status(): void
    {
        $records = Livewire::test(SampleRecordsTable::class)->set('status', 'verified')->viewData('records');

        $this->assertTrue(collect($records->items())->every(fn (array $row): bool => $row['status'] === 'verified'));
    }

    public function test_bulk_action_clears_selection_and_reports(): void
    {
        Livewire::test(SampleRecordsTable::class)
            ->set('selected', ['SMP-0001', 'SMP-0002'])
            ->call('markReviewed')
            ->assertSet('selected', [])
            ->assertDispatched('toast');
    }

    public function test_inspecting_a_row_opens_the_detail_panel(): void
    {
        Livewire::test(SampleRecordsTable::class)
            ->call('inspect', 'SMP-0042')
            ->assertDispatched('open-modal', name: 'sample-record')
            ->assertSee('Sample Person 042');
    }
}
