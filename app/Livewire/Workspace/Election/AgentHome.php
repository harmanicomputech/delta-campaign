<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Models\AgentAssignment;
use App\Models\Incident;
use App\Models\ResultSubmission;
use App\Models\TurnoutObservation;
use App\Models\User;
use App\Services\Election\AgentService;
use App\Services\Election\FieldReports;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** The polling agent's screen: one polling unit, the day's steps in order. */
final class AgentHome extends Component
{
    use WithFileUploads;

    #[Url(except: '')]
    public string $tab = '';

    /** @var array<string, bool> */
    public array $answers = [];

    public string $turnoutCount = '';

    public string $queue = '';

    public string $turnoutNote = '';

    public string $category = '';

    public string $severity = 'medium';

    public string $description = '';

    /** @var list<TemporaryUploadedFile> */
    public array $incidentPhotos = [];

    public string $accredited = '';

    public string $valid = '';

    public string $rejected = '';

    public string $totalCast = '';

    /** @var array<string, string> */
    public array $scores = [];

    /** @var TemporaryUploadedFile|null */
    public $sheetPhoto = null;

    public function mount(): void
    {
        $this->authorize('election.report');
    }

    /** Location is optional: the browser asks the agent, and arrival is recorded either way. */
    public function arrive(?float $lat = null, ?float $lng = null, ?int $accuracy = null): void
    {
        $valid = $lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180;
        $this->act(fn (AgentAssignment $a) => app(FieldReports::class)->arrive($a, $this->actor(), 'web', $valid ? $lat : null, $valid ? $lng : null, $valid ? $accuracy : null), __('election.arrived_ok'));
    }

    public function saveChecklist(FieldReports $reports): void
    {
        $this->act(fn (AgentAssignment $a) => $reports->checklist($a, $this->actor(), $this->answers, 'web'), __('election.checklist_ok'));
    }

    public function saveTurnout(FieldReports $reports): void
    {
        $this->validate(['turnoutCount' => ['required', 'integer', 'min:0', 'max:100000'], 'queue' => ['nullable', Rule::in(['none', 'short', 'long'])], 'turnoutNote' => ['nullable', 'string', 'max:500']],
            [], ['turnoutCount' => __('election.accredited_so_far')]);
        if ($this->act(fn (AgentAssignment $a) => $reports->turnout($a, $this->actor(), (int) $this->turnoutCount, 'web', $this->queue ?: null, $this->turnoutNote ?: null), __('election.turnout_ok'))) {
            $this->reset('turnoutCount', 'queue', 'turnoutNote');
        }
    }

    public function reportIncident(FieldReports $reports): void
    {
        $this->validate([
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'description' => ['required', 'string', 'min:5', 'max:4000'],
            'incidentPhotos' => ['array', 'max:5'],
            'incidentPhotos.*' => ['file', 'max:'.config('campaign.election.evidence_max_kb'), 'mimetypes:'.implode(',', (array) config('campaign.election.evidence_mimes'))],
        ], [], ['category' => __('election.category'), 'description' => __('election.what_happened')]);

        if ($this->act(fn (AgentAssignment $a) => $reports->incident($a, $this->actor(), IncidentCategory::from($this->category), IncidentSeverity::from($this->severity), $this->description, 'web', $this->incidentPhotos), __('election.incident_ok'))) {
            $this->reset('category', 'severity', 'description', 'incidentPhotos');
        }
    }

    public function submitResult(FieldReports $reports): void
    {
        $this->validate([
            'accredited' => ['required', 'integer', 'min:0', 'max:100000'],
            'valid' => ['required', 'integer', 'min:0', 'max:100000'],
            'rejected' => ['required', 'integer', 'min:0', 'max:100000'],
            'totalCast' => ['required', 'integer', 'min:0', 'max:100000'],
            'scores.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sheetPhoto' => ['required', 'file', 'max:'.config('campaign.election.evidence_max_kb'), 'mimetypes:'.implode(',', (array) config('campaign.election.evidence_mimes'))],
        ], [], ['accredited' => __('election.accredited'), 'valid' => __('election.valid'), 'rejected' => __('election.rejected'), 'totalCast' => __('election.total_cast'), 'sheetPhoto' => __('election.sheet_photo')]);

        $figures = ['accredited_voters' => (int) $this->accredited, 'valid_votes' => (int) $this->valid, 'rejected_votes' => (int) $this->rejected, 'total_votes_cast' => (int) $this->totalCast];
        $scores = array_map('intval', array_filter($this->scores, fn ($v) => $v !== '' && $v !== null));

        if ($this->act(fn (AgentAssignment $a) => $reports->result($a, $this->actor(), $figures, $scores, 'web', [$this->sheetPhoto]), __('election.result_ok'))) {
            $this->reset('accredited', 'valid', 'rejected', 'totalCast', 'scores', 'sheetPhoto');
        }
    }

    private function act(callable $action, string $success): bool
    {
        $assignment = app(AgentService::class)->activeAssignmentFor($this->actor());
        if ($assignment === null) {
            $this->dispatch('toast', type: 'error', title: __('election.no_assignment'));

            return false;
        }

        try {
            $action($assignment);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(in_array($field, ['count'], true) ? 'turnoutCount' : ($field === 'photo' ? 'sheetPhoto' : $field), $messages[0]);
            }

            return false;
        } catch (AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return false;
        }

        $this->dispatch('toast', type: 'success', title: $success);

        return true;
    }

    public function render(AgentService $agents): View
    {
        $assignment = $agents->activeAssignmentFor($this->actor());
        if ($assignment !== null && $this->answers === []) {
            $last = $assignment->checkins()->where('kind', 'checklist')->latest('id')->value('answers');
            $this->answers = is_array($last) ? $last : array_fill_keys(array_column($assignment->election->checklist, 'key'), false);
        }

        return view('livewire.workspace.election.agent-home', [
            'assignment' => $assignment,
            'turnout' => $assignment ? TurnoutObservation::query()->where('agent_assignment_id', $assignment->id)->latest('observed_at')->limit(10)->get() : collect(),
            'incidents' => $assignment ? Incident::query()->where('election_id', $assignment->election_id)->where('reported_by', $this->actor()->id)->latest('id')->limit(10)->get() : collect(),
            'results' => $assignment ? ResultSubmission::query()->where('election_id', $assignment->election_id)->where('submitted_by', $this->actor()->id)->latest('id')->limit(5)->get() : collect(),
        ])->title(__('election.my_unit'));
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
