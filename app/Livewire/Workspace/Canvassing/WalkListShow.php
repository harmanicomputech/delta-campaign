<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Canvassing;

use App\Enums\ContactOutcome;
use App\Models\ContactAttempt;
use App\Models\User;
use App\Models\WalkList;
use App\Models\WalkListEntry;
use App\Services\Authorization\AssignableUsers;
use App\Services\Canvassing\CanvassingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class WalkListShow extends Component
{
    /** Outcomes offered as one-tap buttons in the field. */
    public const array QUICK_OUTCOMES = ['reached', 'not_home', 'follow_up_requested', 'refused', 'registered', 'do_not_contact'];

    #[Locked]
    public WalkList $list;

    public string $assigneeSearch = '';

    public ?int $assigneeId = null;

    /** @var array<int|string, string> entry id => notes */
    public array $notes = [];

    public function mount(WalkList $list): void
    {
        $actor = $this->actor();
        $canView = $list->assigned_to === $actor->id || ($actor->can('canvassing.view') && $list->isWithin($actor->geoScope('canvassing.view')));
        abort_unless($canView, 404);

        $this->list = $list;
    }

    /**
     * @param  string  $uuid  generated on the device so a retried tap never records twice
     */
    public function record(int $entryId, string $outcome, string $uuid, CanvassingService $canvassing): void
    {
        validator(['outcome' => $outcome, 'uuid' => $uuid, 'notes' => $this->notes[$entryId] ?? null], [
            'outcome' => ['required', Rule::in(self::QUICK_OUTCOMES)],
            'uuid' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $entry = WalkListEntry::query()->where('walk_list_id', $this->list->id)->findOrFail($entryId);
        $attempt = $canvassing->recordContact($entry, $this->actor(), ContactOutcome::from($outcome), notes: $this->notes[$entryId] ?? null, uuid: $uuid);

        unset($this->notes[$entryId]);
        $this->list->refresh();
        $this->dispatch('toast', type: $attempt->quality_flag->value === 'ok' ? 'success' : 'warning', title: $attempt->quality_flag->value === 'ok' ? __('canvassing.recorded', ['outcome' => $attempt->outcome->label()]) : __('canvassing.recorded_flagged'));
    }

    public function skip(int $entryId, CanvassingService $canvassing): void
    {
        $canvassing->skip(WalkListEntry::query()->where('walk_list_id', $this->list->id)->findOrFail($entryId), $this->actor());
    }

    public function assign(CanvassingService $canvassing): void
    {
        $this->authorize('canvassing.manage');
        $this->validate(['assigneeId' => ['required', 'integer']], [], ['assigneeId' => __('canvassing.assignee')]);
        $canvassing->assign($this->list, User::query()->findOrFail($this->assigneeId), $this->actor());

        $this->list->refresh()->load('assignee:id,name');
        $this->reset('assigneeId', 'assigneeSearch');
        $this->dispatch('close-modal', name: 'assign-walk-list');
        $this->dispatch('toast', type: 'success', title: __('canvassing.assigned_ok', ['name' => $this->list->assignee?->name]));
    }

    public function archive(CanvassingService $canvassing): void
    {
        $canvassing->archive($this->list, $this->actor());
        $this->list->refresh();
        $this->dispatch('toast', type: 'success', title: __('canvassing.archived_ok'));
    }

    public function render(CanvassingService $canvassing, AssignableUsers $assignable): View
    {
        $actor = $this->actor();
        $list = $this->list->load(['ward:id,name', 'lga:id,name', 'assignee:id,name']);
        $canManage = $actor->can('canvassing.manage') && $list->isWithin($actor->geoScope('canvassing.manage'));

        $entries = $list->entries()->orderBy('position')->with(['supporter:id,full_name,reference', 'contactAttempts' => fn ($q) => $q->latest('occurred_at')->with('user:id,name')])->get();

        return view('livewire.workspace.canvassing.walk-list-show', [
            'entries' => $entries,
            'progress' => $list->progress(),
            'canRecord' => $canvassing->canRecord($list, $actor) && $list->status->value !== 'archived',
            'canManage' => $canManage,
            'outcomes' => collect(self::QUICK_OUTCOMES)->mapWithKeys(fn ($o) => [$o => ContactOutcome::from($o)]),
            'candidates' => $canManage ? $assignable->for('canvassing.record', $list->senatorial_district_id, $list->lga_id, $list->ward_id, $this->assigneeSearch) : collect(),
            'outcomeCounts' => ContactAttempt::query()->whereIn('walk_list_entry_id', $entries->pluck('id'))->toBase()->selectRaw('outcome, count(*) as n')->groupBy('outcome')->pluck('n', 'outcome'),
        ])->title($list->name)->layoutData(['breadcrumbs' => [__('nav.groups.field') => null, __('canvassing.title') => route('workspace.canvassing.index'), $list->name => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
