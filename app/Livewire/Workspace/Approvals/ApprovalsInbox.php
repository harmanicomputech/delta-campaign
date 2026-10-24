<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Approvals;

use App\Models\User;
use App\Services\Reporting\ApprovalQueues;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** One place for every decision waiting on this person, limited to their permissions and area. */
final class ApprovalsInbox extends Component
{
    public function mount(): void
    {
        $this->authorize('approvals.view');
    }

    public function render(ApprovalQueues $approvals): View
    {
        $user = Auth::user();
        assert($user instanceof User);
        $queues = $approvals->for($user, 8);

        return view('livewire.workspace.approvals.approvals-inbox', [
            'queues' => $queues,
            'total' => array_sum(array_column($queues, 'count')),
        ])->title(__('approvals.title'))->layoutData(['breadcrumbs' => [__('nav.groups.overview') => null, __('approvals.title') => null]]);
    }
}
