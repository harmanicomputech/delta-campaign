<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Supporters;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\RequestChannel;
use App\Livewire\Concerns\ChecksRecentConfirmation;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\Lga;
use App\Models\Supporter;
use App\Models\User;
use App\Models\Ward;
use App\Rules\NigerianMobileNumber;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Consent\SuppressionService;
use App\Services\Supporters\DuplicateFinder;
use App\Services\Supporters\StaleRecord;
use App\Services\Supporters\SupporterManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class SupporterShow extends Component
{
    use ChecksRecentConfirmation;

    #[Locked]
    public Supporter $supporter;

    public ?string $revealedPhone = null;

    // Correction form
    public string $fullName = '';

    public string $phone = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $language = 'en';

    public bool $wantsToVolunteer = false;

    public string $reason = '';

    #[Locked]
    public int $loadedVersion = 1;

    // Follow-up form
    public string $contactChannel = 'phone';

    public string $contactOutcome = 'reached';

    public string $contactNotes = '';

    public string $followUpOn = '';

    public string $rejectReason = '';

    public function mount(Supporter $supporter): void
    {
        $this->authorize('supporters.view');
        abort_unless(Supporter::query()->whereKey($supporter->id)->visibleTo($this->actor(), 'supporters.view')->exists(), 404);
        $this->supporter = $supporter;
        $this->fillForm();
    }

    public function reveal(SupporterManager $manager): void
    {
        $this->authorize('supporters.view-contact');

        if (! $this->recentlyConfirmed()) {
            $this->requireConfirmation();

            return;
        }

        $this->revealedPhone = $manager->revealPhone($this->supporter, $this->actor());
        $this->dispatch('toast', type: 'info', title: __('supporters.revealed'));
    }

    public function saveCorrection(SupporterManager $manager): void
    {
        $this->authorize('supporters.update');
        $scope = $this->actor()->geoScope('supporters.update');
        abort_unless($this->supporter->isWithin($scope), 403);

        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', new NigerianMobileNumber],
            'lgaId' => ['required', 'integer', Rule::in(array_keys($this->lgaOptions()))],
            'wardId' => ['nullable', 'integer'],
            'language' => ['in:en,pcm'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ], [], ['fullName' => 'full name', 'lgaId' => 'LGA']);

        if ($this->wardId && ! Ward::query()->visibleTo($scope)->where('lga_id', $this->lgaId)->whereKey($this->wardId)->exists()) {
            $this->addError('wardId', __('supporters.ward_not_in_lga'));

            return;
        }

        $changes = [
            'full_name' => $this->fullName,
            'lga_id' => $this->lgaId,
            'ward_id' => $this->wardId,
            'polling_unit_id' => $this->wardId === $this->supporter->ward_id ? $this->supporter->polling_unit_id : null,
            'preferred_language' => $this->language,
            'wants_to_volunteer' => $this->wantsToVolunteer,
        ] + ($this->phone !== '' ? ['phone' => $this->phone] : []);

        try {
            $this->supporter = $manager->correct($this->supporter, $changes, $this->loadedVersion, $this->actor(), $this->reason);
        } catch (StaleRecord) {
            $this->dispatch('toast', type: 'warning', title: __('supporters.stale'));

            return;
        }

        $this->fillForm();
        $this->reset('reason', 'phone');
        $this->dispatch('close-modal', name: 'correct-supporter');
        $this->dispatch('toast', type: 'success', title: __('supporters.saved'));
    }

    public function verify(SupporterManager $manager): void
    {
        $this->authorize('supporters.verify');
        abort_unless($this->supporter->isWithin($this->actor()->geoScope('supporters.verify')), 403);
        $manager->verify($this->supporter, $this->actor());
        $this->supporter->refresh();
        $this->fillForm();
        $this->dispatch('toast', type: 'success', title: __('supporters.verify'));
    }

    public function reject(SupporterManager $manager): void
    {
        $this->authorize('supporters.verify');
        abort_unless($this->supporter->isWithin($this->actor()->geoScope('supporters.verify')), 403);
        $this->validate(['rejectReason' => ['required', 'string', 'min:5', 'max:500']], [], ['rejectReason' => 'reason']);
        $manager->reject($this->supporter, $this->actor(), $this->rejectReason);
        $this->supporter->refresh();
        $this->fillForm();
        $this->reset('rejectReason');
        $this->dispatch('close-modal', name: 'reject-supporter');
    }

    public function logContact(SupporterManager $manager): void
    {
        $this->authorize('supporters.update');
        $this->validate([
            'contactChannel' => [Rule::enum(ContactChannel::class)],
            'contactOutcome' => [Rule::enum(ContactOutcome::class)],
            'contactNotes' => ['nullable', 'string', 'max:2000'],
            'followUpOn' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $manager->logContact(
            $this->supporter,
            $this->actor(),
            ContactChannel::from($this->contactChannel),
            ContactOutcome::from($this->contactOutcome),
            $this->contactNotes,
            $this->followUpOn ? Carbon::parse($this->followUpOn, config('campaign.display_timezone'))->setTime(9, 0) : null,
        );

        $this->reset('contactNotes', 'followUpOn');
        $this->supporter->refresh();
        $this->dispatch('close-modal', name: 'log-contact');
        $this->dispatch('toast', type: 'success', title: __('supporters.logged'));
    }

    public function setConsent(string $channel, string $status, ConsentService $consent): void
    {
        $this->authorize('supporters.update');
        abort_if($this->supporter->phone_hash === null, 422);

        try {
            $consent->record(
                (string) $this->supporter->phone_hash,
                ConsentChannel::from($channel),
                ConsentPurpose::CampaignUpdates,
                ConsentStatus::from($status),
                RequestChannel::Staff,
                $this->supporter,
                ['recorded_via' => 'workspace', 'note' => 'Recorded by staff on the person\'s instruction'],
                $this->actor(),
            );
        } catch (NoPublishedNotice $e) {
            $this->dispatch('toast', type: 'danger', title: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', title: __('supporters.consents'));
    }

    public function merge(int $duplicateId, SupporterManager $manager): void
    {
        $this->authorize('supporters.update');
        $duplicate = Supporter::query()->visibleTo($this->actor(), 'supporters.update')->findOrFail($duplicateId);
        $manager->merge($this->supporter, $duplicate, $this->actor());
        $this->supporter->refresh();
        $this->dispatch('toast', type: 'success', title: __('supporters.merged'));
    }

    public function render(ConsentService $consent, DuplicateFinder $duplicates): View
    {
        $actor = $this->actor();
        $this->supporter->load(['lga', 'ward', 'pollingUnit', 'registeredBy', 'verifier']);
        $hash = $this->supporter->phone_hash;

        return view('livewire.workspace.supporters.supporter-show', [
            'consentSummary' => $hash ? $consent->summary($hash) : [],
            'consentHistory' => $hash ? ConsentRecord::query()->with('recorder')->where('phone_hash', $hash)->orderByDesc('recorded_at')->orderByDesc('id')->limit(30)->get() : collect(),
            'suppressed' => $hash ? collect(ConsentChannel::cases())->mapWithKeys(fn ($c) => [$c->value => app(SuppressionService::class)->isSuppressed($hash, $c)]) : collect(),
            'contacts' => $this->supporter->contactAttempts()->with('user')->latest('occurred_at')->limit(20)->get(),
            'duplicates' => $actor->can('supporters.update') ? $duplicates->candidates($this->supporter, $actor) : collect(),
            'requests' => $actor->can('dsr.manage') ? $this->supporter->dataSubjectRequests()->latest()->get() : collect(),
            'audit' => $actor->can('audit.view') ? AuditLog::query()->where('subject_type', $this->supporter->getMorphClass())->where('subject_id', (string) $this->supporter->id)->with('actor')->latest('id')->limit(15)->get() : collect(),
            'lgas' => $this->lgaOptions(),
            'wards' => $this->lgaId ? Ward::query()->visibleTo($actor->geoScope('supporters.update'))->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id')->all() : [],
            'channels' => ContactChannel::options(),
            'outcomes' => ContactOutcome::options(),
        ])->title($this->supporter->full_name)->layoutData(['breadcrumbs' => [
            __('supporters.title') => route('workspace.supporters.index'),
            $this->supporter->reference => null,
        ]]);
    }

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    /** @return array<int, string> */
    private function lgaOptions(): array
    {
        return Lga::query()->visibleTo($this->actor()->geoScope('supporters.update'))->orderBy('name')->pluck('name', 'id')->all()
            + ($this->supporter->lga_id ? [$this->supporter->lga_id => $this->supporter->lga?->name ?? ''] : []);
    }

    private function fillForm(): void
    {
        $this->fullName = $this->supporter->full_name;
        $this->lgaId = $this->supporter->lga_id;
        $this->wardId = $this->supporter->ward_id;
        $this->language = $this->supporter->preferred_language;
        $this->wantsToVolunteer = $this->supporter->wants_to_volunteer;
        $this->loadedVersion = $this->supporter->version;
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
