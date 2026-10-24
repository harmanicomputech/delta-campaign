<?php

declare(strict_types=1);

namespace App\Livewire\DesignSystem;

use App\Rules\NigerianMobileNumber;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Reference form: server-side validation with inline, accessible errors,
 * validated as fields lose focus and again on submit. Nothing is persisted.
 */
final class SampleForm extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $area = '';

    public string $notes = '';

    /** @var list<string> */
    public array $channels = [];

    public bool $acknowledged = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', new NigerianMobileNumber],
            'area' => ['required', Rule::in(array_keys($this->areas()))],
            'notes' => ['nullable', 'string', 'max:500'],
            'channels' => ['array'],
            'channels.*' => [Rule::in(['sms', 'whatsapp'])],
            'acknowledged' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'full name', 'phone' => 'phone number', 'acknowledged' => 'acknowledgement'];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function submit(): void
    {
        $validated = $this->validate();

        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Form is valid',
            message: 'Normalised to '.NigerianPhoneNumber::mask((string) NigerianPhoneNumber::normalise($validated['phone'])).'. Sample form — nothing was saved.',
        );

        $this->reset();
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /**
     * @return array<string, string>
     */
    public function areas(): array
    {
        return collect(range(1, 6))->mapWithKeys(fn (int $i): array => ["area-{$i}" => sprintf('Sample area %02d', $i)])->all();
    }

    public function render(): View
    {
        return view('livewire.design-system.sample-form');
    }
}
