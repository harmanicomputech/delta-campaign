<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Events;

use App\Enums\EventStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\CampaignEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

final class EventsIndex extends Component
{
    use WithTableState;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'upcoming')]
    public string $when = 'upcoming';

    protected function sortableColumns(): array
    {
        return ['starts_at'];
    }

    public function render(): View
    {
        $user = Auth::user();
        assert($user instanceof User);

        // Statewide events are visible to everyone with events.view; area events only within scope.
        $scope = $user->geoScope('events.view');
        $query = CampaignEvent::query()->with(['lga:id,name', 'ward:id,name'])->withCount(['registrations as registered_count' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->when(! $scope->statewide, fn ($q) => $q->where(fn ($q) => $q->whereNull('lga_id')->orWhere(fn ($q) => $scope->apply($q, CampaignEvent::geoColumns()))))
            ->when(EventStatus::tryFrom($this->status), fn ($q, EventStatus $s) => $q->where('status', $s))
            ->when($this->when === 'upcoming', fn ($q) => $q->where('starts_at', '>=', now()->startOfDay()))
            ->when($this->when === 'past', fn ($q) => $q->where('starts_at', '<', now()->startOfDay()))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('title_en', 'ilike', $this->likeTerm())->orWhere('venue', 'ilike', $this->likeTerm())));

        return view('livewire.workspace.events.events-index', [
            'events' => $this->applySort($query, 'starts_at', $this->when === 'past' ? 'desc' : 'asc')->paginate($this->perPage),
            'statuses' => EventStatus::options(),
        ])->title(__('events.title'))->layoutData(['breadcrumbs' => [__('nav.groups.field') => null, __('events.title') => null]]);
    }
}
