<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Exports;

use App\Enums\ExportStatus;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

final class ExportsIndex extends Component
{
    use WithWorkspacePagination;

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $exports = ExportJob::query()->where('requested_by', $user->id)->latest()->paginate(20);

        return view('livewire.workspace.exports.exports-index', [
            'exports' => $exports,
            'pending' => $exports->getCollection()->contains(fn (ExportJob $e) => in_array($e->status, [ExportStatus::Queued, ExportStatus::Running], true)),
            'downloadUrl' => fn (ExportJob $e): string => URL::temporarySignedRoute('workspace.exports.download', now()->addMinutes(10), ['export' => $e->id]),
        ])->title(__('supporters.exports'))->layoutData(['breadcrumbs' => [__('shell.workspace') => route('workspace.dashboard'), __('supporters.exports') => null]]);
    }
}
