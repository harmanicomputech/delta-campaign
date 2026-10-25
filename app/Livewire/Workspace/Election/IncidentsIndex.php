<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Election;
use App\Models\Incident;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Incident queue: open incidents first, most severe first. Refreshes every 20 seconds. */
final class IncidentsIndex extends Component
{
    use WithTableState;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $severity = '';

    #[Url(except: '')]
    public string $category = '';

    public function mount(): void
    {
        $this->authorize('incidents.view');
    }

    protected function sortableColumns(): array
    {
        return ['occurred_at'];
    }

    public function render(): View
    {
        $election = Election::current();
        $query = Incident::query()->where('election_id', $election?->id)->visibleTo(Auth::user(), 'incidents.view')
            ->with(['pollingUnit:id,code,name', 'lga:id,name', 'assignee:id,name'])->withCount('evidence')
            ->when($this->status === 'open', fn ($q) => $q->whereIn('status', [IncidentStatus::Reported, IncidentStatus::Acknowledged, IncidentStatus::Escalated]))
            ->when(IncidentStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s))
            ->when(IncidentSeverity::tryFrom($this->severity), fn ($q, $s) => $q->where('severity', $s))
            ->when(IncidentCategory::tryFrom($this->category), fn ($q, $c) => $q->where('category', $c))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference', 'ilike', $this->likeTerm())->orWhereHas('pollingUnit', fn ($p) => $p->where('code', 'ilike', $this->likeTerm()))))
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end");

        return view('livewire.workspace.election.incidents-index', [
            'election' => $election,
            'incidents' => $this->applySort($query, 'occurred_at', 'desc')->paginate($this->perPage),
        ])->title(__('election.incidents'))->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.incidents') => null]]);
    }
}
