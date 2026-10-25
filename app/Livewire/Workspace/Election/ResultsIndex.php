<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Enums\ResultStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Election;
use App\Models\OfficialResult;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\Election\ElectionMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Result submissions to review, and polling units where verified and official figures differ. */
final class ResultsIndex extends Component
{
    use WithTableState;

    #[Url(except: 'review')]
    public string $view = 'review';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('results.view');
    }

    protected function sortableColumns(): array
    {
        return ['created_at'];
    }

    public function render(ElectionMetrics $metrics): View
    {
        $user = Auth::user();
        assert($user instanceof User);
        $election = Election::current();
        $scope = $user->geoScope('results.view');

        $submissions = ResultSubmission::query()->where('election_id', $election?->id)->visibleTo($scope)
            ->with(['pollingUnit:id,code,name', 'lga:id,name', 'submitter:id,name'])->withCount('evidence')
            ->when($this->view === 'review' && $this->status === '', fn ($q) => $q->whereIn('status', [ResultStatus::Submitted, ResultStatus::Flagged]))
            ->when(ResultStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s))
            ->when($this->view === 'discrepancies', fn ($q) => $q->whereIn('polling_unit_id', $election ? $metrics->discrepantUnits($election, $scope) : [])->where('status', ResultStatus::Verified))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference', 'ilike', $this->likeTerm())->orWhereHas('pollingUnit', fn ($p) => $p->where('code', 'ilike', $this->likeTerm())->orWhere('name', 'ilike', $this->likeTerm()))));

        return view('livewire.workspace.election.results-index', [
            'election' => $election,
            'submissions' => $this->applySort($submissions, 'created_at', 'desc')->paginate($this->perPage),
            'official' => $election ? OfficialResult::query()->where('election_id', $election->id)->visibleTo($scope)->count() : 0,
        ])->title(__('election.results'))->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.results') => null]]);
    }
}
