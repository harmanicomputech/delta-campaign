<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Supporters;

use App\Enums\SupporterSource;
use App\Enums\VerificationStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Lga;
use App\Models\PrivacyNotice;
use App\Models\Supporter;
use App\Models\User;
use App\Models\Ward;
use App\Rules\NigerianMobileNumber;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Exports\SupporterExportService;
use App\Services\Supporters\SupporterManager;
use App\Services\Supporters\SupporterRegistrar;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

final class SupportersIndex extends Component
{
    use WithTableState;

    #[Url(except: '')]
    public string $lga = '';

    #[Url(except: '')]
    public string $ward = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $source = '';

    /** @var list<int> */
    public array $selected = [];

    // Registration form
    public string $fullName = '';

    public string $phone = '';

    public ?int $formLgaId = null;

    public ?int $formWardId = null;

    public string $pollingUnitCode = '';

    public string $language = 'en';

    public bool $wantsToVolunteer = false;

    public bool $consentSms = false;

    public bool $consentWhatsapp = false;

    public bool $adult = false;

    // Export
    public bool $includeContact = false;

    public function mount(): void
    {
        $this->authorize('supporters.view');
    }

    protected function sortableColumns(): array
    {
        return ['full_name', 'created_at', 'reference'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['lga', 'status', 'source'], true)) {
            $this->resetPage();
            if ($property === 'lga') {
                $this->ward = '';
            }
        }
        if ($property === 'ward') {
            $this->resetPage();
        }
        if ($property === 'formLgaId') {
            $this->formWardId = null;
        }
    }

    public function register(SupporterRegistrar $registrar): void
    {
        $this->authorize('supporters.create');

        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', new NigerianMobileNumber],
            'formLgaId' => ['required', 'integer'],
            'formWardId' => ['nullable', 'integer'],
            'pollingUnitCode' => ['nullable', 'string', 'max:40'],
            'language' => ['in:en,pcm'],
            'adult' => ['accepted'],
        ], [], ['fullName' => 'full name', 'formLgaId' => 'LGA', 'adult' => 'age confirmation']);

        // The chosen location must be inside the registrar's own scope.
        $scope = $this->actor()->geoScope('supporters.create');
        $allowed = $this->formWardId
            ? Ward::query()->visibleTo($scope)->whereKey($this->formWardId)->exists()
            : Lga::query()->visibleTo($scope)->whereKey($this->formLgaId)->exists();
        if (! $allowed) {
            $this->addError('formLgaId', __('users.not_allowed'));

            return;
        }

        try {
            $result = $registrar->register(
                ['full_name' => $this->fullName, 'phone' => $this->phone, 'lga_id' => $this->formLgaId, 'ward_id' => $this->formWardId, 'polling_unit_code' => $this->pollingUnitCode ?: null, 'preferred_language' => $this->language, 'wants_to_volunteer' => $this->wantsToVolunteer],
                SupporterSource::Staff,
                ['sms' => $this->consentSms, 'whatsapp' => $this->consentWhatsapp],
                $this->adult,
                $this->actor(),
                ['form' => 'workspace', 'notice' => PrivacyNotice::current()?->version],
            );
        } catch (NoPublishedNotice $e) {
            $this->addError('consentSms', $e->getMessage());

            return;
        }

        $this->reset('fullName', 'phone', 'formWardId', 'pollingUnitCode', 'wantsToVolunteer', 'consentSms', 'consentWhatsapp', 'adult');
        $this->dispatch('close-modal', name: 'register-supporter');
        $this->dispatch('toast', type: $result->created ? 'success' : 'info', title: $result->created
            ? __('supporters.registered_ok', ['name' => $result->supporter->full_name])
            : __('supporters.already_registered', ['reference' => $result->supporter->reference]));
    }

    public function verifySelected(SupporterManager $manager): void
    {
        $this->authorize('supporters.verify');

        $records = Supporter::query()->visibleTo($this->actor(), 'supporters.verify')->whereIn('id', $this->selected)
            ->whereNull('erased_at')->where('verification_status', '!=', VerificationStatus::Verified)->get();

        foreach ($records as $supporter) {
            $manager->verify($supporter, $this->actor());
        }

        $this->selected = [];
        $this->dispatch('toast', type: 'success', title: trans_choice('supporters.verified_count', $records->count(), ['count' => $records->count()]));
    }

    public function export(SupporterExportService $exports): void
    {
        $this->authorize('supporters.export');

        $exports->request($this->actor(), [
            'lga_id' => $this->lga ?: null,
            'ward_id' => $this->ward ?: null,
            'verification_status' => $this->status ?: null,
            'source' => $this->source ?: null,
        ], $this->includeContact && $this->actor()->can('supporters.view-contact'));

        $this->includeContact = false;
        $this->dispatch('close-modal', name: 'export-supporters');
        $this->dispatch('toast', type: 'success', title: __('supporters.export_started'));
        $this->redirectRoute('workspace.exports.index', navigate: false);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $term = trim($this->search);
        $phoneHash = $term !== '' && preg_match('/\d{7,}/', $term) ? PhoneHasher::fromInput($term) : null;
        $scope = $actor->geoScope('supporters.view');

        $query = Supporter::query()->visibleTo($actor, 'supporters.view')
            ->with(['lga:id,name', 'ward:id,name'])
            ->whereNull('erased_at')
            ->when($term !== '', fn (Builder $q) => $phoneHash
                ? $q->where('phone_hash', $phoneHash)
                : $q->where(fn (Builder $w) => $w->where('full_name', 'ilike', $this->likeTerm())->orWhere('reference', strtoupper($term))))
            ->when($this->lga !== '', fn (Builder $q) => $q->where('lga_id', (int) $this->lga))
            ->when($this->ward !== '', fn (Builder $q) => $q->where('ward_id', (int) $this->ward))
            ->when(VerificationStatus::tryFrom($this->status), fn (Builder $q, $s) => $q->where('verification_status', $s))
            ->when(SupporterSource::tryFrom($this->source), fn (Builder $q, $s) => $q->where('source', $s))
            ->selectRaw("supporters.*, (select status from consent_records c where c.phone_hash = supporters.phone_hash and c.channel = 'sms' and c.purpose = 'campaign_updates' order by c.recorded_at desc, c.id desc limit 1) as sms_consent")
            ->selectRaw("(select status from consent_records c where c.phone_hash = supporters.phone_hash and c.channel = 'whatsapp' and c.purpose = 'campaign_updates' order by c.recorded_at desc, c.id desc limit 1) as whatsapp_consent");

        $createScope = $actor->geoScope('supporters.create');

        return view('livewire.workspace.supporters.supporters-index', [
            'supporters' => $this->applySort($query, 'created_at', 'desc')->paginate($this->perPage),
            'lgas' => Lga::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lga !== '' ? Ward::query()->visibleTo($scope)->where('lga_id', (int) $this->lga)->orderBy('name')->pluck('name', 'id') : collect(),
            'formLgas' => Lga::query()->visibleTo($createScope)->orderBy('name')->pluck('name', 'id')
                ->union($createScope->wardIds !== [] ? Lga::query()->whereIn('id', Ward::query()->whereIn('id', $createScope->wardIds)->select('lga_id'))->pluck('name', 'id') : collect()),
            'formWards' => $this->formLgaId ? Ward::query()->visibleTo($createScope)->where('lga_id', $this->formLgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'notice' => PrivacyNotice::current(),
            'statuses' => VerificationStatus::options(),
            'sources' => SupporterSource::options(),
        ])->title(__('supporters.title'))->layoutData(['breadcrumbs' => [__('nav.groups.people') => null, __('supporters.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
