<div>
    <x-ui.page-header :title="__('notifications.title')" :description="__('notifications.intro')">
        <x-slot:actions>
            @if ($unread > 0)
                <x-ui.button variant="secondary" icon="check-check" wire:click="markAllRead">{{ __('notifications.mark_all') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
            <x-ui.switch wire:model.live="unreadOnly" :label="__('notifications.unread_only')" />
            <span class="text-xs text-subtle">{{ trans_choice('notifications.unread_count', $unread, ['count' => $unread]) }}</span>
        </div>
        <ul class="divide-y divide-line">
            @forelse ($notifications as $notification)
                <li class="flex gap-3 px-5 py-4" wire:key="n-{{ $notification->id }}">
                    <span @class(['flex size-9 shrink-0 items-center justify-center rounded-full', 'bg-primary-soft text-primary' => ! $notification->read_at, 'bg-surface-sunken text-subtle' => $notification->read_at])>
                        <x-icon :name="$notification->data['icon'] ?? 'bell'" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        @php($url = (string) ($notification->data['url'] ?? ''))
                        @if (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                            <a href="{{ $url }}" wire:click="markRead('{{ $notification->id }}')" @class(['text-sm hover:text-primary', 'font-semibold text-fg' => ! $notification->read_at, 'text-muted' => $notification->read_at])>{{ $notification->data['title'] ?? '' }}</a>
                        @else
                            <p @class(['text-sm', 'font-semibold text-fg' => ! $notification->read_at, 'text-muted' => $notification->read_at])>{{ $notification->data['title'] ?? '' }}</p>
                        @endif
                        @if (! empty($notification->data['body'])) <p class="text-[0.8125rem] text-muted">{{ $notification->data['body'] }}</p> @endif
                        <p class="mt-0.5 text-xs text-subtle">{{ $notification->created_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }}</p>
                    </div>
                    @unless ($notification->read_at)
                        <x-ui.button size="sm" variant="ghost" square icon="check" wire:click="markRead('{{ $notification->id }}')"><span class="sr-only">{{ __('notifications.mark_read') }}</span></x-ui.button>
                    @endunless
                </li>
            @empty
                <li><x-ui.empty-state icon="bell" :title="$unreadOnly ? __('notifications.none_unread') : __('notifications.none')" :description="__('notifications.none_help')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line">{{ $notifications->links() }}</div>
    </x-ui.card>
</div>
