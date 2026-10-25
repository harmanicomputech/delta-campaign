<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Enums\Availability;
use App\Enums\VolunteerSkill;
use App\Livewire\Concerns\GuardsPublicForms;
use App\Livewire\Concerns\VerifiesPhone;
use App\Models\Lga;
use App\Models\PrivacyNotice;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Services\Volunteers\VolunteerService;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class VolunteerSignup extends Component
{
    use GuardsPublicForms, VerifiesPhone;

    public string $fullName = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    /** @var list<string> */
    public array $skills = [];

    /** @var list<string> */
    public array $availability = [];

    public bool $adult = false;

    public bool $done = false;

    public bool $existing = false;

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function apply(VolunteerService $volunteers): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150', "regex:/^[\\p{L}][\\p{L} .'-]+$/u"],
            'lgaId' => ['required', 'integer', Rule::exists('lgas', 'id')],
            'wardId' => ['nullable', 'integer', Rule::exists('wards', 'id')->where('lga_id', $this->lgaId)],
            'skills.*' => [Rule::enum(VolunteerSkill::class)],
            'availability.*' => [Rule::enum(Availability::class)],
            'adult' => ['accepted'],
        ], [], ['fullName' => __('supporters.full_name'), 'lgaId' => 'LGA', 'adult' => __('public.age_confirmation')]);

        if ($this->verifiedPhone === null) {
            $this->addError('phone', __('public.verify_first'));

            return;
        }

        $this->guard('volunteer', 5);
        $this->existing = Volunteer::query()->where('phone_hash', PhoneHasher::hash($this->verifiedPhone))->exists();
        $volunteers->apply(['full_name' => $this->fullName, 'phone' => $this->verifiedPhone, 'lga_id' => $this->lgaId, 'ward_id' => $this->wardId, 'skills' => $this->skills, 'availability' => $this->availability], 'web');
        $this->done = true;
    }

    public function render(): View
    {
        return view('livewire.public.volunteer-signup', [
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'skillOptions' => VolunteerSkill::options(),
            'availabilityOptions' => Availability::options(),
            'notice' => PrivacyNotice::current(),
            'masked' => $this->maskedVerifiedPhone(),
        ]);
    }
}
