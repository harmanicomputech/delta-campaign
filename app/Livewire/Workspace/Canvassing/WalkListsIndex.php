<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Canvassing;

use App\Enums\QualityFlag;
use App\Enums\WalkListStatus;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\ContactAttempt;
use App\Models\Lga;
use App\Models\User;
use App\Models\WalkList;
use App\Models\Ward;
use App\Services\Canvassing\CanvassingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

final class WalkListsIndex extends Component
{
    use WithWorkspacePagination;

    /** mine | area | review */
    #[Url(except: '')]
    public string $view = '';

    #[Url(except: '')]
    public string $status = '';

    // New list
    public string $name = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $stops = '';

    public bool $includeSupporters = false;

    public string $dueOn = '';

    public string $notes = '';

    public function mount(): void
    {
        $actor = $this->actor();
        abort_unless($actor->can('canvassing.view') || $actor->can('canvassing.record'), 403);

        // Managers land on their area; field workers on their own work.
        $allowed = array_keys($this->views());
        if (! in_array($this->view, $allowed, true)) {
            $this->view = $actor->can('canvassing.manage') && in_array('area', $allowed, true) ? 'area' : $allowed[0];
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'status'], true)) {
            $this->resetPage();
        }
        if ($property === 'lgaId') {
            $this->wardId = null;
        }
    }

    public function create(CanvassingService $canvassing): void
    {
        $this->authorize('canvassing.manage');
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'lgaId' => ['required', 'integer'],
            'wardId' => ['required', 'integer'],
            'stops' => ['nullable', 'string', 'max:10000'],
            'dueOn' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['wardId' => __('volunteers.ward'), 'lgaId' => 'LGA', 'dueOn' => __('canvassing.due_on')]);

        $ward = Ward::query()->visibleTo($this->actor(), 'canvassing.manage')->where('lga_id', $this->lgaId)->findOrFail($this->wardId);
        $list = $canvassing->createWalkList(
            $this->actor(), $this->name, $ward, preg_split('/\R/', $this->stops) ?: [], $this->includeSupporters,
            $this->dueOn !== '' ? Carbon::parse($this->dueOn) : null, $this->notes !== '' ? $this->notes : null,
        );

        $this->redirectRoute('workspace.canvassing.show', $list);
    }

    public function review(int $id, string $flag, CanvassingService $canvassing): void
    {
        $this->authorize('canvassing.manage');
        $attempt = ContactAttempt::query()->visibleTo($this->actor(), 'canvassing.manage')->findOrFail($id);
        $canvassing->review($attempt, QualityFlag::from($flag), $this->actor());
        $this->dispatch('toast', type: 'success', title: __('canvassing.reviewed'));
    }

    /** @return array<string, string> */
    private function views(): array
    {
        $actor = $this->actor();

        return array_filter([
            'mine' => $actor->can('canvassing.record') ? __('canvassing.my_lists') : null,
            'area' => $actor->can('canvassing.view') ? __('canvassing.area_lists') : null,
            'review' => $actor->can('canvassing.manage') ? __('canvassing.review_queue') : null,
        ]);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $manageScope = $actor->geoScope('canvassing.manage');

        $lists = WalkList::query()->with(['ward:id,name', 'lga:id,name', 'assignee:id,name'])
            ->withCount(['entries', 'entries as done_count' => fn (Builder $q) => $q->where('status', '!=', 'pending')])
            ->when($this->view === 'mine', fn (Builder $q) => $q->where('assigned_to', $actor->id)->where('status', '!=', WalkListStatus::Archived))
            ->when($this->view === 'area', fn (Builder $q) => $q->visibleTo($actor, 'canvassing.view'))
            ->when(WalkListStatus::tryFrom($this->status), fn (Builder $q, WalkListStatus $s) => $q->where('status', $s))
            ->orderByRaw("case status when 'in_progress' then 0 when 'assigned' then 1 when 'draft' then 2 when 'completed' then 3 else 4 end")
            ->orderBy('due_on')->latest('id');

        $flagged = $this->view === 'review'
            ? ContactAttempt::query()->visibleTo($actor, 'canvassing.manage')->where('quality_flag', '!=', QualityFlag::Ok)->whereNull('reviewed_at')
                ->with(['user:id,name', 'supporter:id,full_name,reference', 'walkListEntry:id,label,walk_list_id', 'walkListEntry.walkList:id,uuid,name'])
                ->latest('occurred_at')->paginate(25)
            : null;

        return view('livewire.workspace.canvassing.walk-lists-index', [
            'views' => $this->views(),
            'lists' => $this->view === 'review' ? null : $lists->paginate(20),
            'flagged' => $flagged,
            'flaggedCount' => $actor->can('canvassing.manage') ? ContactAttempt::query()->visibleTo($actor, 'canvassing.manage')->where('quality_flag', '!=', QualityFlag::Ok)->whereNull('reviewed_at')->count() : 0,
            'statuses' => WalkListStatus::options(),
            'lgas' => $actor->can('canvassing.manage') ? Lga::query()->whereIn('id', Ward::query()->visibleTo($manageScope)->select('lga_id'))->orderBy('name')->pluck('name', 'id') : collect(),
            'wards' => $this->lgaId ? Ward::query()->visibleTo($manageScope)->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
        ])->title(__('canvassing.title'))->layoutData(['breadcrumbs' => [__('nav.groups.field') => null, __('canvassing.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
