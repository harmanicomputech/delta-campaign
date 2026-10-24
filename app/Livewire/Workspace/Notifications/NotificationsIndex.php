<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Notifications;

use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

final class NotificationsIndex extends Component
{
    use WithWorkspacePagination;

    #[Url(except: false)]
    public bool $unreadOnly = false;

    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        $this->actor()->notifications()->findOrFail($id)->markAsRead();
    }

    public function markAllRead(): void
    {
        $this->actor()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('toast', type: 'success', title: __('notifications.all_read'));
    }

    public function render(): View
    {
        $user = $this->actor();

        return view('livewire.workspace.notifications.notifications-index', [
            'notifications' => ($this->unreadOnly ? $user->unreadNotifications() : $user->notifications())->latest()->paginate(25),
            'unread' => $user->unreadNotifications()->count(),
        ])->title(__('notifications.title'))->layoutData(['breadcrumbs' => [__('notifications.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
