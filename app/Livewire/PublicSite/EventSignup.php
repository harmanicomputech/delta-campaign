<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Livewire\Concerns\GuardsPublicForms;
use App\Livewire\Concerns\VerifiesPhone;
use App\Models\CampaignEvent;
use App\Services\Events\EventService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class EventSignup extends Component
{
    use GuardsPublicForms, VerifiesPhone;

    #[Locked]
    public int $eventId;

    public string $fullName = '';

    public ?string $ticketUrl = null;

    public function mount(CampaignEvent $event): void
    {
        $this->eventId = $event->id;
    }

    public function register(EventService $events): void
    {
        $this->validate(['fullName' => ['required', 'string', 'min:2', 'max:150']], [], ['fullName' => __('supporters.full_name')]);

        if ($this->verifiedPhone === null) {
            $this->addError('phone', __('public.verify_first'));

            return;
        }

        $this->guard('event', 10);

        try {
            ['registration' => $registration] = $events->register(CampaignEvent::query()->findOrFail($this->eventId), $this->fullName, $this->verifiedPhone, 'web');
        } catch (ValidationException $e) {
            $this->addError('form', collect($e->errors())->flatten()->first());

            return;
        }

        $this->ticketUrl = route('public.events.ticket', $registration->ticket_token);
    }

    public function render(): View
    {
        $event = CampaignEvent::query()->findOrFail($this->eventId);

        return view('livewire.public.event-signup', ['event' => $event, 'spaces' => $event->spacesLeft(), 'masked' => $this->maskedVerifiedPhone()]);
    }
}
