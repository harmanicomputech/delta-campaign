<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Events;

use App\Enums\RegistrationStatus;
use App\Models\CampaignEvent;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class EventShow extends Component
{
    use WithPagination;

    #[Locked]
    public CampaignEvent $event;

    public string $reason = '';

    public string $attendeeName = '';

    public string $attendeePhone = '';

    public string $filter = '';

    public function mount(CampaignEvent $event): void
    {
        $user = $this->actor();
        abort_unless($event->lga_id === null || $event->isWithin($user->geoScope('events.view')), 404); // Out of scope: do not reveal that it exists.
        $this->event = $event;
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function publish(EventService $events): void
    {
        $this->act(fn () => $events->publish($this->event, $this->actor()), __('events.published'));
    }

    public function cancel(EventService $events): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:300']], [], ['reason' => __('volunteers.reason')]);
        $notified = 0;
        $this->act(function () use ($events, &$notified): void {
            $notified = $events->cancel($this->event, $this->actor(), $this->reason);
        }, '');
        $this->dispatch('close-modal', name: 'event-cancel');
        $this->dispatch('toast', type: 'success', title: trans_choice('events.cancelled_notified', $notified, ['count' => $notified]));
    }

    /** Walk-up registration at the door (the person is present and gives their number). */
    public function addAttendee(EventService $events): void
    {
        abort_unless($events->canManage($this->event, $this->actor()), 403);
        $this->validate(['attendeeName' => ['required', 'string', 'min:2', 'max:150'], 'attendeePhone' => ['required', 'string', 'max:20']], [], ['attendeeName' => __('public.full_name'), 'attendeePhone' => __('public.phone')]);

        try {
            $result = $events->register($this->event, $this->attendeeName, $this->attendeePhone, 'walk_in', $this->actor());
            $events->checkIn($result['registration']->ticket_token, $this->actor());
        } catch (ValidationException $e) {
            $this->addError('attendeePhone', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('attendeeName', 'attendeePhone');
        $this->dispatch('close-modal', name: 'event-walk-in');
        $this->dispatch('toast', type: 'success', title: __('events.walk_in_added'));
    }

    private function act(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return;
        }

        $this->event->refresh();
        if ($success !== '') {
            $this->dispatch('toast', type: 'success', title: $success);
        }
    }

    public function render(EventService $events): View
    {
        $event = $this->event->load(['lga:id,name', 'ward:id,name', 'cover', 'creator:id,name']);
        $canManage = $events->canManage($event, $this->actor());

        $counts = $event->registrations()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('livewire.workspace.events.event-show', [
            'canManage' => $canManage,
            'counts' => $counts,
            // Names of registrants are personal data: only organisers who manage this event see the list.
            'registrations' => $canManage ? $event->registrations()->when(RegistrationStatus::tryFrom($this->filter), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25) : null,
        ])->title($event->title_en)->layoutData(['breadcrumbs' => [__('events.title') => route('workspace.events.index'), $event->title_en => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
