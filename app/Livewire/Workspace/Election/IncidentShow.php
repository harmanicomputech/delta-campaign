<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Models\Incident;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Election\IncidentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class IncidentShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public Incident $incident;

    public string $note = '';

    public string $action = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public function mount(Incident $incident, IncidentService $incidents): void
    {
        abort_unless($incidents->canView($incident, $this->actor()), 404);
        $this->incident = $incident;
    }

    public function acknowledge(IncidentService $incidents): void
    {
        $this->act(fn () => $incidents->acknowledge($this->incident, $this->actor()));
    }

    public function startAction(string $action): void
    {
        abort_unless(in_array($action, ['escalate', 'resolve', 'dismiss', 'reopen'], true), 400);
        $this->action = $action;
        $this->reset('note');
        $this->resetErrorBag();
        $this->dispatch('open-modal', name: 'incident-action');
    }

    public function confirmAction(IncidentService $incidents): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:2000']], [], ['note' => __('election.note')]);
        $method = $this->action;
        abort_unless(in_array($method, ['escalate', 'resolve', 'dismiss', 'reopen'], true), 400);
        if ($this->act(fn () => $incidents->{$method}($this->incident, $this->actor(), $this->note))) {
            $this->dispatch('close-modal', name: 'incident-action');
        }
    }

    public function upload(IncidentService $incidents): void
    {
        $this->validate(['file' => ['required', 'file', 'max:'.config('campaign.election.evidence_max_kb'), 'mimetypes:'.implode(',', (array) config('campaign.election.evidence_mimes'))]]);
        if ($this->act(fn () => $incidents->addEvidence($this->incident, $this->actor(), $this->file))) {
            $this->reset('file');
        }
    }

    private function act(callable $action): bool
    {
        try {
            $action();
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return false;
        }
        $this->incident->refresh();
        $this->dispatch('toast', type: 'success', title: __('election.incident_updated'));

        return true;
    }

    public function render(IncidentService $incidents): View
    {
        $incident = $this->incident->load(['pollingUnit', 'ward:id,name', 'lga:id,name', 'reporter:id,name', 'assignee:id,name', 'evidence.uploader:id,name']);

        return view('livewire.workspace.election.incident-show', [
            'canManage' => $incidents->canManage($incident, $this->actor()),
            'canSeeEvidence' => $this->actor()->hasPermission('evidence.view') || $incidents->canManage($incident, $this->actor()),
            'history' => WorkflowTransition::query()->where('subject_type', $incident->getMorphClass())->where('subject_id', $incident->id)->with('user:id,name')->latest('id')->get(),
        ])->title($incident->reference)->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.incidents') => route('workspace.election.incidents.index'), $incident->reference => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
