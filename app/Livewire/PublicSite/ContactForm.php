<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Livewire\Concerns\GuardsPublicForms;
use App\Models\ContactMessage;
use App\Models\Lga;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class ContactForm extends Component
{
    use GuardsPublicForms;

    public string $name = '';

    public string $contact = '';

    public string $topic = 'general';

    public ?int $lgaId = null;

    public string $message = '';

    public bool $agree = false;

    public ?string $reference = null;

    public function send(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'contact' => ['nullable', 'string', 'max:150'],
            'topic' => [Rule::in(ContactMessage::TOPICS)],
            'lgaId' => ['nullable', 'integer', Rule::exists('lgas', 'id')],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'agree' => ['accepted'],
        ], [], ['name' => __('public.your_name'), 'message' => __('public.message'), 'agree' => __('public.contact_agree_short')]);

        $this->guard('contact', 5);

        $saved = ContactMessage::query()->create([
            'name' => mb_substr(trim($this->name), 0, 150),
            'contact_encrypted' => trim($this->contact) !== '' ? trim($this->contact) : null,
            'topic' => $this->topic,
            'message_encrypted' => trim($this->message),
            'lga_id' => $this->lgaId,
            'language' => app()->getLocale() === 'pcm' ? 'pcm' : 'en',
        ]);

        $this->reference = $saved->reference;
        $this->reset('name', 'contact', 'message', 'agree');
    }

    public function render(): View
    {
        return view('livewire.public.contact-form', ['lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'), 'topics' => ContactMessage::TOPICS]);
    }
}
