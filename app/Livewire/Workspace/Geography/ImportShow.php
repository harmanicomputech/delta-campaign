<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Geography;

use App\Enums\ImportRowStatus;
use App\Livewire\Concerns\ChecksRecentConfirmation;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\ImportJob;
use App\Models\User;
use App\Services\Imports\ImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

/** Preview of a validated import (counts, row errors and warnings) and the confirm/cancel decision. */
final class ImportShow extends Component
{
    use ChecksRecentConfirmation, WithWorkspacePagination;

    #[Locked]
    public ImportJob $import;

    /** all | invalid | warnings | create | update | unchanged */
    #[Url(except: 'all')]
    public string $filter = 'all';

    public function mount(ImportJob $import): void
    {
        $this->authorize('geography.import');
        $this->import = $import;
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function confirm(ImportService $imports): void
    {
        $this->authorize('geography.import');

        if (! $this->recentlyConfirmed()) {
            $this->requireConfirmation();

            return;
        }

        $user = Auth::user();
        assert($user instanceof User);

        try {
            $imports->confirm($this->import->refresh(), $user);
        } catch (LogicException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->import->refresh();
        $this->dispatch('toast', type: 'success', title: __('geography.import_confirmed'));
    }

    public function cancel(ImportService $imports): void
    {
        $this->authorize('geography.import');

        try {
            $imports->cancel($this->import->refresh());
        } catch (LogicException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->import->refresh();
        $this->dispatch('toast', type: 'success', title: __('geography.import_cancelled'));
    }

    public function render(): View
    {
        $import = $this->import->refresh()->load(['creator:id,name', 'confirmer:id,name']);

        $rows = $import->rows()->orderBy('row_number')
            ->when($this->filter === 'invalid', fn ($q) => $q->where('status', ImportRowStatus::Invalid))
            ->when($this->filter === 'warnings', fn ($q) => $q->whereNotNull('warnings'))
            ->when(in_array($this->filter, ['create', 'update', 'unchanged'], true), fn ($q) => $q->where('action', $this->filter)->where('status', '!=', ImportRowStatus::Invalid));

        return view('livewire.workspace.geography.import-show', [
            'rows' => $rows->paginate(50),
            'actions' => $import->rows()->toBase()->whereNotNull('action')->where('status', '!=', ImportRowStatus::Invalid->value)->selectRaw('action, count(*) as n')->groupBy('action')->pluck('n', 'action'),
            'identical' => $import->previousIdenticalImport(),
            'working' => in_array($import->status->value, ['uploaded', 'validating', 'importing'], true),
        ])->title($import->original_name)->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('geography.title') => route('workspace.geography'), __('geography.imports') => route('workspace.geography.imports'), $import->original_name => null]]);
    }
}
