<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

/** Server-side pagination rendered with the workspace pagination view. */
trait WithWorkspacePagination
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'pagination.workspace';
    }
}
