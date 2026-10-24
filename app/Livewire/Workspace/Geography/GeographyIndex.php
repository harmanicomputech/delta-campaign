<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Geography;

use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\SenatorialDistrict;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Read-only browser for districts → LGAs → wards → polling units, limited to
 * the viewer's scope. Reference data changes only through imports.
 */
final class GeographyIndex extends Component
{
    use WithWorkspacePagination;

    #[Url(except: null)]
    public ?int $district = null;

    #[Url(except: null)]
    public ?int $lga = null;

    #[Url(except: null)]
    public ?int $ward = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('geography.view');
    }

    public function open(string $level, int $id): void
    {
        match ($level) {
            'district' => [$this->district, $this->lga, $this->ward] = [$id, null, null],
            'lga' => [$this->lga, $this->ward] = [$id, null],
            'ward' => $this->ward = $id,
            default => null,
        };
        $this->resetPage();
    }

    public function back(string $level): void
    {
        match ($level) {
            'root' => [$this->district, $this->lga, $this->ward] = [null, null, null],
            'district' => [$this->lga, $this->ward] = [null, null],
            'lga' => $this->ward = null,
            default => null,
        };
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $actor = Auth::user();
        assert($actor instanceof User);
        $scope = $actor->geoScope('geography.view');

        // Tampered ids outside the scope are ignored.
        $ward = $this->ward ? Ward::query()->visibleTo($scope)->with('lga.senatorialDistrict')->find($this->ward) : null;
        // An LGA is open to someone scoped to the whole LGA, or to one of its wards.
        $lga = $ward?->lga ?? ($this->lga ? Lga::query()->with('senatorialDistrict')
            ->where(fn ($q) => $q->visibleTo($scope)->orWhereIn('id', Ward::query()->visibleTo($scope)->select('lga_id')))->find($this->lga) : null);
        $district = $lga?->senatorialDistrict ?? ($this->district && $scope->statewide ? SenatorialDistrict::query()->find($this->district) : null);

        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        [$level, $items] = match (true) {
            $term !== '' => ['search', PollingUnit::query()->visibleTo($scope)->with(['ward:id,name', 'lga:id,name'])
                ->where(fn ($q) => $q->where('code', 'ilike', $like)->orWhere('name', 'ilike', $like))->orderBy('code')->paginate(25)],
            $ward !== null => ['polling_units', PollingUnit::query()->where('ward_id', $ward->id)->orderBy('code')->paginate(50)],
            $lga !== null => ['wards', Ward::query()->visibleTo($scope)->where('lga_id', $lga->id)->withCount('pollingUnits')->orderBy('code')->paginate(50)],
            $district !== null || ! $scope->statewide => ['lgas', Lga::query()->visibleTo($scope)
                ->when($district, fn ($q) => $q->where('senatorial_district_id', $district->id))
                ->when(! $scope->statewide && $district === null, fn ($q) => $q->orWhereIn('id', Ward::query()->visibleTo($scope)->select('lga_id')))
                ->withCount(['wards', 'pollingUnits'])->orderBy('name')->paginate(50)],
            default => ['districts', SenatorialDistrict::query()->withCount(['lgas', 'wards', 'pollingUnits'])->orderBy('name')->paginate(50)],
        };

        return view('livewire.workspace.geography.geography-index', [
            'level' => $level,
            'items' => $items,
            'districtModel' => $district,
            'lgaModel' => $lga,
            'wardModel' => $ward,
            'totals' => [
                'districts' => SenatorialDistrict::query()->count(),
                'lgas' => Lga::query()->count(),
                'wards' => Ward::query()->count(),
                'units' => PollingUnit::query()->count(),
                'sample_units' => PollingUnit::query()->where('is_sample', true)->count(),
            ],
        ])->title(__('geography.title'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('geography.title') => null]]);
    }
}
