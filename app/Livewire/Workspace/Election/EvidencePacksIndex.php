<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Models\Election;
use App\Models\EvidencePack;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Ward;
use App\Services\Election\EvidencePackService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class EvidencePacksIndex extends Component
{
    public string $scopeType = 'lga';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $unitCode = '';

    public function mount(): void
    {
        $this->authorize('evidence.export');
    }

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function request(EvidencePackService $packs): void
    {
        $this->validate([
            'scopeType' => [Rule::in(['state', 'lga', 'ward', 'polling_unit'])],
            'lgaId' => ['required_if:scopeType,lga,ward', 'nullable', 'integer'],
            'wardId' => ['required_if:scopeType,ward', 'nullable', 'integer'],
            'unitCode' => ['required_if:scopeType,polling_unit', 'nullable', 'string', 'max:40'],
        ], [], ['lgaId' => 'LGA', 'wardId' => __('volunteers.ward'), 'unitCode' => __('election.unit_code')]);

        $election = Election::current();
        $scopeId = match ($this->scopeType) {
            'lga' => $this->lgaId,
            'ward' => $this->wardId,
            'polling_unit' => PollingUnit::query()->where('code', strtoupper(trim($this->unitCode)))->value('id'),
            default => null,
        };
        if ($election === null || ($this->scopeType !== 'state' && $scopeId === null)) {
            $this->addError('unitCode', __('election.unit_not_found'));

            return;
        }

        try {
            $packs->request($this->actor(), $election, $this->scopeType, $scopeId);
        } catch (AuthorizationDenied $e) {
            $this->addError('scopeType', $e->getMessage());

            return;
        }
        $this->dispatch('toast', type: 'success', title: __('election.pack_requested'));
    }

    public function render(): View
    {
        $user = $this->actor();
        $scope = $user->geoScope('evidence.export');
        $packs = EvidencePack::query()->where('requested_by', $user->id)->latest('id')->limit(20)->get();

        return view('livewire.workspace.election.evidence-packs', [
            'packs' => $packs,
            'statewide' => $scope->statewide,
            'lgas' => Lga::query()->when(! $scope->statewide, fn ($q) => $q->where(fn ($q) => $q->whereIn('id', $scope->lgaIds)->orWhereIn('senatorial_district_id', $scope->districtIds)))->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'links' => $packs->where('status', 'ready')->mapWithKeys(fn (EvidencePack $p) => [$p->id => URL::temporarySignedRoute('workspace.election.packs.download', now()->addMinutes(30), $p)]),
            'polling' => $packs->contains(fn ($p) => in_array($p->status, ['queued', 'building'], true)),
        ])->title(__('election.packs'))->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.packs') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
