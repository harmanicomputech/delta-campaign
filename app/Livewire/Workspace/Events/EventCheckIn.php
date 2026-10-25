<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Events;

use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Door check-in. Scanning a ticket's QR code opens this page with the token in
 * the URL; nothing changes until the organiser presses "Check in" (GET requests
 * never modify data). Tokens can also be typed or pasted.
 */
final class EventCheckIn extends Component
{
    public string $token = '';

    public ?int $registrationId = null;

    public ?string $result = null;

    public function mount(?string $token = null): void
    {
        $this->authorize('events.manage');

        if ($token !== null) {
            $this->token = $token;
            $this->lookup();
        }
    }

    public function lookup(): void
    {
        $this->reset('registrationId', 'result');
        $this->resetErrorBag();
        $token = $this->extractToken($this->token);

        $registration = $token ? EventRegistration::query()->with('event')->where('ticket_token', $token)->first() : null;

        if ($registration === null) {
            $this->addError('token', __('events.ticket_unknown'));

            return;
        }
        if (! app(EventService::class)->canManage($registration->event, $this->actor())) {
            $this->addError('token', __('events.ticket_other_area'));

            return;
        }

        $this->token = $token;
        $this->registrationId = $registration->id;
    }

    public function checkIn(EventService $events): void
    {
        try {
            $registration = $events->checkIn($this->extractToken($this->token), $this->actor());
        } catch (ValidationException $e) {
            $this->addError('token', (string) collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationDenied) {
            $this->addError('token', __('events.ticket_other_area'));

            return;
        }

        $this->result = $registration->wasChanged('status') ? 'checked_in' : 'already';
        $this->registrationId = $registration->id;
    }

    public function next(): void
    {
        $this->reset('token', 'registrationId', 'result');
        $this->resetErrorBag();
    }

    /** Accepts the bare token or the full check-in URL from the QR code. */
    private function extractToken(string $input): string
    {
        $input = trim($input);
        if (preg_match('/([A-Za-z0-9]{40})\/?$/', $input, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    public function render(): View
    {
        return view('livewire.workspace.events.event-check-in', [
            'registration' => $this->registrationId ? EventRegistration::query()->with('event')->find($this->registrationId) : null,
        ])->title(__('events.check_in'))->layoutData(['breadcrumbs' => [__('events.title') => route('workspace.events.index'), __('events.check_in') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
