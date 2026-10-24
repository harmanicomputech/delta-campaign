<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Volunteers;

use App\Enums\Availability;
use App\Enums\VolunteerSkill;
use App\Enums\VolunteerStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\Lga;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Rules\NigerianMobileNumber;
use App\Services\Volunteers\VolunteerService;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class VolunteersIndex extends Component
{
    use WithTableState;

    /** applications | active | all */
    #[Url(except: 'applications')]
    public string $view = 'applications';

    #[Url(except: '')]
    public string $lga = '';

    #[Url(except: '')]
    public string $ward = '';

    #[Url(except: '')]
    public string $skill = '';

    // Application form
    public string $fullName = '';

    public string $phone = '';

    public ?int $formLgaId = null;

    public ?int $formWardId = null;

    /** @var list<string> */
    public array $skills = [];

    /** @var list<string> */
    public array $availability = [];

    // Rejection
    public ?int $rejectId = null;

    public string $rejectReason = '';

    public function mount(): void
    {
        $this->authorize('volunteers.view');

        if (! in_array($this->view, ['applications', 'active', 'all'], true)) {
            $this->view = 'applications';
        }
    }

    protected function sortableColumns(): array
    {
        return ['full_name', 'applied_at'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'lga', 'ward', 'skill'], true)) {
            $this->resetPage();
        }
        if ($property === 'lga') {
            $this->ward = '';
        }
        if ($property === 'formLgaId') {
            $this->formWardId = null;
        }
    }

    public function addApplication(VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.manage');

        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', new NigerianMobileNumber],
            'formLgaId' => ['required', 'integer'],
            'formWardId' => ['required', 'integer'],
            'skills' => ['array'],
            'skills.*' => [Rule::enum(VolunteerSkill::class)],
            'availability' => ['array'],
            'availability.*' => [Rule::enum(Availability::class)],
        ], [], ['fullName' => __('volunteers.full_name'), 'formLgaId' => 'LGA', 'formWardId' => __('volunteers.ward')]);

        if (! Ward::query()->visibleTo($this->actor(), 'volunteers.manage')->whereKey($this->formWardId)->where('lga_id', $this->formLgaId)->exists()) {
            $this->addError('formWardId', __('users.not_allowed'));

            return;
        }

        $existing = Volunteer::query()->where('phone_hash', PhoneHasher::fromInput($this->phone))->exists();
        $volunteer = $volunteers->apply(['full_name' => $this->fullName, 'phone' => $this->phone, 'lga_id' => $this->formLgaId, 'ward_id' => $this->formWardId, 'skills' => $this->skills, 'availability' => $this->availability], 'staff', $this->actor());

        $this->reset('fullName', 'phone', 'formWardId', 'skills', 'availability');
        $this->dispatch('close-modal', name: 'add-volunteer');
        $this->dispatch('toast', type: $existing ? 'info' : 'success', title: $existing ? __('volunteers.already_applied') : __('volunteers.application_added', ['name' => $volunteer->full_name]));
    }

    public function approve(int $id, VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.approve');
        $volunteer = $this->find($id, 'volunteers.approve');
        $volunteers->approve($volunteer, $this->actor());
        $this->dispatch('toast', type: 'success', title: __('volunteers.approved_ok', ['name' => $volunteer->full_name]), message: __('volunteers.approved_help'));
    }

    public function confirmReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->dispatch('open-modal', 'reject-volunteer');
    }

    public function reject(VolunteerService $volunteers): void
    {
        $this->authorize('volunteers.approve');
        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:500']], [], ['rejectReason' => __('volunteers.reason')]);
        $volunteer = $this->find((int) $this->rejectId, 'volunteers.approve');
        $volunteers->reject($volunteer, $this->actor(), $this->rejectReason);

        $this->reset('rejectId', 'rejectReason');
        $this->dispatch('close-modal', name: 'reject-volunteer');
        $this->dispatch('toast', type: 'success', title: __('volunteers.rejected_ok', ['name' => $volunteer->full_name]));
    }

    public function render(): View
    {
        $actor = $this->actor();
        $scope = $actor->geoScope('volunteers.view');
        $term = trim($this->search);
        $phoneHash = $term !== '' && preg_match('/\d{7,}/', $term) ? PhoneHasher::fromInput($term) : null;

        $base = Volunteer::query()->visibleTo($scope);

        $query = (clone $base)->with(['lga:id,name', 'ward:id,name'])
            ->when($this->view === 'applications', fn (Builder $q) => $q->where('status', VolunteerStatus::Applied))
            ->when($this->view === 'active', fn (Builder $q) => $q->where('status', VolunteerStatus::Approved))
            ->when($term !== '', fn (Builder $q) => $phoneHash ? $q->where('phone_hash', $phoneHash) : $q->where('full_name', 'ilike', $this->likeTerm()))
            ->when($this->lga !== '', fn (Builder $q) => $q->where('lga_id', (int) $this->lga))
            ->when($this->ward !== '', fn (Builder $q) => $q->where('ward_id', (int) $this->ward))
            ->when(VolunteerSkill::tryFrom($this->skill), fn (Builder $q, VolunteerSkill $s) => $q->whereJsonContains('skills', $s->value));

        $manageScope = $actor->geoScope('volunteers.manage');

        return view('livewire.workspace.volunteers.volunteers-index', [
            'volunteers' => $this->applySort($query, $this->view === 'applications' ? 'applied_at' : 'full_name', $this->view === 'applications' ? 'asc' : 'asc')->paginate($this->perPage),
            'counts' => (clone $base)->toBase()->selectRaw("count(*) filter (where status = 'applied') as applications, count(*) filter (where status = 'approved') as active, count(*) as total")->first(),
            'lgas' => Lga::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lga !== '' ? Ward::query()->visibleTo($scope)->where('lga_id', (int) $this->lga)->orderBy('name')->pluck('name', 'id') : collect(),
            'formLgas' => $actor->can('volunteers.manage') ? Lga::query()->whereIn('id', Ward::query()->visibleTo($manageScope)->select('lga_id'))->orderBy('name')->pluck('name', 'id') : collect(),
            'formWards' => $this->formLgaId ? Ward::query()->visibleTo($manageScope)->where('lga_id', $this->formLgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'skillOptions' => VolunteerSkill::options(),
            'availabilityOptions' => Availability::options(),
        ])->title(__('volunteers.title'))->layoutData(['breadcrumbs' => [__('nav.groups.people') => null, __('volunteers.title') => null]]);
    }

    private function find(int $id, string $permission): Volunteer
    {
        return Volunteer::query()->visibleTo($this->actor(), $permission)->findOrFail($id);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
