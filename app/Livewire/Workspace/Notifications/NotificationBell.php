<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Top-bar bell: unread count and the latest notifications. Polls once a minute (not in low-data mode). */
final class NotificationBell extends Component
{
    /** False in low-data mode: the count refreshes on page loads and when the bell is opened. */
    public bool $poll = true;

    public function markAllRead(): void
    {
        $this->actor()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function open(string $id): void
    {
        $notification = $this->actor()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        // Only internal paths are followed.
        $this->redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('workspace.notifications'), navigate: false);
    }

    public function render(): View
    {
        $user = $this->actor();

        return view('livewire.workspace.notifications.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->limit(6)->get(),
        ]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
