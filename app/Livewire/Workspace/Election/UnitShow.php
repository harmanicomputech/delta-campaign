<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Enums\OfficialSource;
use App\Enums\ResultStatus;
use App\Models\AgentAssignment;
use App\Models\Election;
use App\Models\Incident;
use App\Models\OfficialResult;
use App\Models\PollingUnit;
use App\Models\ResultSubmission;
use App\Models\TurnoutObservation;
use App\Models\User;
use App\Services\Election\ResultReview;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Everything about one polling unit on election day, with result review and official figures. */
final class UnitShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public PollingUnit $unit;

    #[Url(except: '')]
    public string $submission = '';

    #[Locked]
    public ?int $reviewId = null;

    public string $reviewAction = '';

    public string $note = '';

    public string $source = 'inec_irev';

    public string $reference = '';

    public string $offAccredited = '';

    public string $offValid = '';

    public string $offRejected = '';

    public string $offTotal = '';

    /** @var array<string, string> */
    public array $offScores = [];

    /** @var TemporaryUploadedFile|null */
    public $document = null;

    public function mount(PollingUnit $unit): void
    {
        $user = $this->actor();
        $allowed = collect(['polling-agents.view', 'incidents.view', 'results.view'])
            ->contains(fn ($p) => $user->hasPermission($p) && $user->geoScope($p)->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id));
        abort_unless($allowed, 404);
        $this->unit = $unit;
    }

    public function verify(int $id, ResultReview $review): void
    {
        $this->act(fn () => $review->verify($this->submissionFor($id), $this->actor()), __('election.verified_ok'));
    }

    public function startReview(int $id, string $action): void
    {
        abort_unless(in_array($action, ['flag', 'reject'], true), 400);
        $this->reviewId = $id;
        $this->reviewAction = $action;
        $this->reset('note');
        $this->dispatch('open-modal', name: 'result-review');
    }

    public function confirmReview(ResultReview $review): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']], [], ['note' => __('election.note')]);
        $method = $this->reviewAction === 'reject' ? 'reject' : 'flag';
        if ($this->act(fn () => $review->{$method}($this->submissionFor((int) $this->reviewId), $this->actor(), $this->note), __('election.review_saved'))) {
            $this->dispatch('close-modal', name: 'result-review');
        }
    }

    public function startOfficial(): void
    {
        $election = Election::current();
        $existing = $election ? OfficialResult::query()->where('election_id', $election->id)->where('polling_unit_id', $this->unit->id)->first() : null;
        if ($existing) {
            $this->fill(['source' => $existing->source->value, 'reference' => $existing->source_reference, 'offAccredited' => (string) ($existing->accredited_voters ?? ''),
                'offValid' => (string) $existing->valid_votes, 'offRejected' => (string) $existing->rejected_votes, 'offTotal' => (string) $existing->total_votes_cast,
                'offScores' => array_map('strval', $existing->scores)]);
        }
        $this->dispatch('open-modal', name: 'official-result');
    }

    public function saveOfficial(ResultReview $review): void
    {
        $this->validate([
            'source' => ['required', Rule::enum(OfficialSource::class)],
            'reference' => ['required', 'string', 'min:3', 'max:500'],
            'offAccredited' => ['nullable', 'integer', 'min:0'],
            'offValid' => ['required', 'integer', 'min:0'],
            'offRejected' => ['required', 'integer', 'min:0'],
            'offTotal' => ['required', 'integer', 'min:0'],
            'offScores.*' => ['nullable', 'integer', 'min:0'],
            'document' => ['nullable', 'file', 'max:'.config('campaign.election.evidence_max_kb'), 'mimetypes:'.implode(',', (array) config('campaign.election.evidence_mimes'))],
        ], [], ['reference' => __('election.source_reference'), 'offValid' => __('election.valid'), 'offRejected' => __('election.rejected'), 'offTotal' => __('election.total_cast')]);

        $election = Election::current();
        if ($election === null) {
            return;
        }
        $figures = ['accredited_voters' => $this->offAccredited === '' ? null : (int) $this->offAccredited, 'valid_votes' => (int) $this->offValid, 'rejected_votes' => (int) $this->offRejected, 'total_votes_cast' => (int) $this->offTotal];
        $scores = array_map('intval', array_filter($this->offScores, fn ($v) => $v !== ''));
        if ($this->act(fn () => $review->recordOfficial($this->actor(), $election, $this->unit, OfficialSource::from($this->source), $this->reference, $figures, $scores, $this->document), __('election.official_saved'))) {
            $this->reset('document');
            $this->dispatch('close-modal', name: 'official-result');
        }
    }

    private function submissionFor(int $id): ResultSubmission
    {
        return ResultSubmission::query()->where('polling_unit_id', $this->unit->id)->findOrFail($id);
    }

    private function act(callable $action, string $success): bool
    {
        try {
            $action();
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return false;
        }
        $this->dispatch('toast', type: 'success', title: $success);

        return true;
    }

    public function render(ResultReview $review): View
    {
        $user = $this->actor();
        $election = Election::current()?->load('parties');
        $unit = $this->unit->load(['ward:id,name', 'lga:id,name']);
        $id = $election?->id;
        $canResults = $user->hasPermission('results.view');

        $submissions = $canResults ? ResultSubmission::query()->where('election_id', $id)->where('polling_unit_id', $unit->id)->with(['submitter:id,name', 'reviewer:id,name', 'evidence.uploader:id,name'])->latest('id')->get() : collect();
        $official = $canResults ? OfficialResult::query()->where('election_id', $id)->where('polling_unit_id', $unit->id)->with(['recorder:id,name', 'evidence.uploader:id,name'])->first() : null;
        $verified = $submissions->firstWhere('status', ResultStatus::Verified);

        return view('livewire.workspace.election.unit-show', [
            'election' => $election,
            'agents' => $user->hasPermission('polling-agents.view') ? AgentAssignment::query()->where('election_id', $id)->where('polling_unit_id', $unit->id)->with(['user:id,name,phone_last4', 'checkins' => fn ($q) => $q->latest('occurred_at')])->get() : collect(),
            'turnout' => TurnoutObservation::query()->where('election_id', $id)->where('polling_unit_id', $unit->id)->latest('observed_at')->limit(12)->get(),
            'incidents' => $user->hasPermission('incidents.view') ? Incident::query()->where('election_id', $id)->where('polling_unit_id', $unit->id)->latest('occurred_at')->get() : collect(),
            'submissions' => $submissions,
            'official' => $official,
            'comparison' => $review->compare($verified, $official),
            'canResults' => $canResults,
            'canVerify' => $user->hasPermission('results.verify') && $unit->isWithin($user->geoScope('results.verify')),
            'canOfficial' => $user->hasPermission('results.official') && $user->geoScope('results.official')->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id),
            'canSeeEvidence' => $user->hasPermission('evidence.view'),
        ])->title($unit->code)->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), $unit->code => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
