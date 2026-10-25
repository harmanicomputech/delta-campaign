<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\RapidResponse;

use App\Enums\Verdict;
use App\Models\RapidResponseCase;
use App\Models\User;
use App\Services\Authorization\AssignableUsers;
use App\Services\RapidResponse\RapidResponseService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class CaseShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public RapidResponseCase $case;

    public string $note = '';

    public string $verdict = '';

    public string $finding = '';

    public string $reason = '';

    public bool $noResponse = false;

    public ?int $assigneeId = null;

    public string $evidenceDescription = '';

    /** @var TemporaryUploadedFile|null */
    public $evidence = null;

    public function mount(RapidResponseCase $case): void
    {
        $this->authorize('rapid-response.view');
        $this->case = $case;
        $this->verdict = $case->verdict?->value ?? '';
        $this->finding = (string) $case->finding;
        $this->assigneeId = $case->assigned_to;
    }

    public function addNote(RapidResponseService $cases): void
    {
        $this->validate(['note' => ['required', 'string', 'min:2', 'max:4000']]);
        $this->act(fn () => $cases->addNote($this->case, $this->actor(), $this->note), __('rapid.note_added'));
        $this->reset('note');
    }

    public function uploadEvidence(RapidResponseService $cases): void
    {
        $this->validate(['evidence' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,txt'], 'evidenceDescription' => ['nullable', 'string', 'max:1000']]);
        $this->act(fn () => $cases->addEvidence($this->case, $this->actor(), $this->evidence, $this->evidenceDescription ?: null), __('rapid.evidence_added'));
        $this->reset('evidence', 'evidenceDescription');
    }

    public function assign(RapidResponseService $cases): void
    {
        $this->validate(['assigneeId' => ['required', 'integer']]);
        $assignee = User::query()->find($this->assigneeId);
        if ($assignee === null) {
            $this->addError('assigneeId', __('rapid.assignee_invalid'));

            return;
        }
        $this->act(fn () => $cases->assign($this->case, $assignee, $this->actor()), __('rapid.assigned'));
    }

    public function saveFinding(RapidResponseService $cases): void
    {
        $this->validate(['verdict' => ['required', Rule::enum(Verdict::class)], 'finding' => ['required', 'string', 'min:10', 'max:10000']], [], ['finding' => __('rapid.finding')]);
        $this->act(fn () => $cases->recordFinding($this->case, $this->actor(), Verdict::from($this->verdict), $this->finding), __('rapid.finding_saved'));
    }

    public function startInvestigation(RapidResponseService $cases): void
    {
        $this->act(fn () => $cases->startInvestigation($this->case, $this->actor()), __('rapid.moved'));
    }

    public function requestApproval(RapidResponseService $cases): void
    {
        $this->act(fn () => $cases->requestApproval($this->case, $this->actor()), __('rapid.moved'));
    }

    public function clearLegal(RapidResponseService $cases): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => __('rapid.legal_note')]);
        $this->act(fn () => $cases->clearLegal($this->case, $this->actor(), $this->reason), __('rapid.moved'));
        $this->reset('reason');
        $this->dispatch('close-modal', name: 'rr-legal');
    }

    public function approve(RapidResponseService $cases): void
    {
        $this->act(fn () => $cases->approve($this->case, $this->actor()), __('rapid.approved'));
    }

    public function close(RapidResponseService $cases): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => __('volunteers.reason')]);
        $this->act(fn () => $cases->close($this->case, $this->actor(), $this->reason, $this->noResponse), __('rapid.closed'));
        $this->reset('reason', 'noResponse');
        $this->dispatch('close-modal', name: 'rr-close');
    }

    private function act(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return;
        }

        $this->case->refresh();
        $this->dispatch('toast', type: 'success', title: $success);
    }

    public function render(AssignableUsers $assignable): View
    {
        $case = $this->case->load(['assignee:id,name', 'creator:id,name', 'content:id,status,title_en', 'notes' => fn ($q) => $q->with('user:id,name')->latest('id')]);
        $canManage = $this->actor()->hasPermission('rapid-response.manage');

        return view('livewire.workspace.rapid-response.case-show', [
            'canManage' => $canManage,
            'canApprove' => $this->actor()->hasPermission('rapid-response.approve'),
            'isCreator' => $case->created_by === $this->actor()->id,
            'officers' => $canManage ? $assignable->for('rapid-response.manage', null, null, null)->pluck('name', 'id')->all() : [],
        ])->title($case->reference)->layoutData(['breadcrumbs' => [__('rapid.title') => route('workspace.rapid-response.index'), $case->reference => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
