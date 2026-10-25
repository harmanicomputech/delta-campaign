<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Models\Election;
use App\Models\User;
use App\Services\Election\ElectionMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Election-day overview for coordinators and leadership, limited to their area. Refreshes every 30 seconds. */
final class OperationsHub extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->canAny(['polling-agents.view', 'incidents.view', 'results.view']), 403);
    }

    public function render(ElectionMetrics $metrics): View
    {
        $user = Auth::user();
        assert($user instanceof User);
        $election = Election::current()?->load('parties');
        $scope = $user->geoScope($user->hasPermission('results.view') ? 'results.view' : ($user->hasPermission('polling-agents.view') ? 'polling-agents.view' : 'incidents.view'));

        return view('livewire.workspace.election.operations-hub', [
            'election' => $election,
            'summary' => $election ? $metrics->summary($election, $scope) : null,
            'breakdown' => $election ? $metrics->breakdown($election, $scope) : [],
            'verified' => $election && $user->hasPermission('results.view') ? $metrics->verifiedTally($election, $scope) : null,
            'official' => $election && $user->hasPermission('results.view') ? $metrics->officialTally($election, $scope) : null,
            'discrepancies' => $election && $user->hasPermission('results.view') ? count($metrics->discrepantUnits($election, $scope)) : 0,
            'byWard' => ! $scope->statewide && $scope->districtIds === [],
        ])->title(__('election.title'))->layoutData(['breadcrumbs' => [__('nav.groups.election_day') => null, __('election.title') => null]]);
    }
}
