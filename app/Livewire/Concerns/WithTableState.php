<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Search, allow-listed sorting and server-side pagination for workspace tables.
 * Components declare sortableColumns() and apply sorting with applySort().
 */
trait WithTableState
{
    use WithWorkspacePagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public int $perPage = 25;

    /** @return list<string> */
    abstract protected function sortableColumns(): array;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<*>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected function applySort($query, string $default, string $defaultDirection = 'asc')
    {
        $column = in_array($this->sort, $this->sortableColumns(), true) ? $this->sort : $default;
        $direction = $this->sort === '' ? $defaultDirection : ($this->direction === 'desc' ? 'desc' : 'asc');

        return $query->orderBy($column, $direction);
    }

    /** Escapes LIKE wildcards in user input. */
    protected function likeTerm(): string
    {
        return '%'.addcslashes(trim($this->search), '%_\\').'%';
    }
}
