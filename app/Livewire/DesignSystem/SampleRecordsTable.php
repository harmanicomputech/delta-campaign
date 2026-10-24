<?php

declare(strict_types=1);

namespace App\Livewire\DesignSystem;

use App\Livewire\Concerns\WithWorkspacePagination;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reference implementation of the workspace table pattern: search, filters,
 * sorting, server-side pagination, row selection and a detail slide-over.
 *
 * Uses deterministic SAMPLE rows held in memory. Module tables (Phase 4+)
 * follow the same pattern against indexed, geographically scoped queries.
 */
final class SampleRecordsTable extends Component
{
    use WithWorkspacePagination;

    private const int ROWS = 137;

    private const array STATUSES = ['verified', 'pending', 'review'];

    private const array SOURCES = ['web', 'ussd', 'field'];

    private const array SORTABLE = ['reference', 'name', 'area', 'status', 'updated_at'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'updated_at')]
    public string $sort = 'updated_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    public int $perPage = 10;

    /** @var list<string> */
    public array $selected = [];

    public ?string $inspecting = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function inspect(string $reference): void
    {
        $this->inspecting = $reference;
        $this->dispatch('open-modal', name: 'sample-record');
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function markReviewed(): void
    {
        $count = count($this->selected);
        $this->selected = [];

        $this->dispatch('toast', type: 'info', title: trans_choice(':count sample row acknowledged|:count sample rows acknowledged', $count, ['count' => $count]), message: 'Sample data — nothing was changed.');
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function inspected(): ?array
    {
        return $this->inspecting ? $this->rows()->firstWhere('reference', $this->inspecting) : null;
    }

    public function render(): View
    {
        $filtered = $this->rows()
            ->when($this->search !== '', function (Collection $rows): Collection {
                $needle = mb_strtolower(trim($this->search));

                return $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['reference'].' '.$row['name'].' '.$row['area']), $needle));
            })
            ->when(in_array($this->status, self::STATUSES, true), fn (Collection $rows): Collection => $rows->where('status', $this->status))
            ->sortBy($this->sort === 'updated_at' ? fn (array $row): int => $row['updated_at']->getTimestamp() : $this->sort, SORT_NATURAL, $this->direction === 'desc')
            ->values();

        $page = $this->getPage();
        $perPage = in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10;

        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
        );

        return view('livewire.design-system.sample-records-table', [
            'records' => $paginator,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $epoch = Carbon::parse('2026-10-01 08:00:00', 'UTC');

        return collect(range(1, self::ROWS))->map(fn (int $i): array => [
            'reference' => sprintf('SMP-%04d', $i),
            'name' => sprintf('Sample Person %03d', $i),
            'area' => sprintf('Sample area %02d', ($i * 7) % 25 + 1),
            'status' => self::STATUSES[($i * 5) % 3],
            'source' => self::SOURCES[($i * 3) % 3],
            'updated_at' => $epoch->copy()->addMinutes(($i * 977) % 30_000),
        ]);
    }
}
