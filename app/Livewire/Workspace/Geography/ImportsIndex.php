<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Geography;

use App\Enums\ImportType;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\ImportJob;
use App\Models\User;
use App\Services\Imports\ImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class ImportsIndex extends Component
{
    use WithFileUploads, WithWorkspacePagination;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public function mount(): void
    {
        $this->authorize('geography.import');
    }

    /** Validation starts as soon as a file is chosen; nothing is written until the preview is confirmed. */
    public function updatedFile(ImportService $imports): void
    {
        $this->upload($imports);
    }

    public function upload(ImportService $imports): void
    {
        $this->authorize('geography.import');
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) config('campaign.imports.max_file_kb')]], [], ['file' => __('geography.csv_file')]);

        $user = Auth::user();
        assert($user instanceof User);

        $job = $imports->start($this->file->getRealPath(), $this->file->getClientOriginalName(), ImportType::PollingUnits, $user);
        $this->reset('file');

        $this->redirectRoute('workspace.geography.imports.show', $job);
    }

    public function render(): View
    {
        return view('livewire.workspace.geography.imports-index', [
            'jobs' => ImportJob::query()->with('creator:id,name')->latest()->paginate(20),
        ])->title(__('geography.imports'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('geography.title') => route('workspace.geography'), __('geography.imports') => null]]);
    }
}
