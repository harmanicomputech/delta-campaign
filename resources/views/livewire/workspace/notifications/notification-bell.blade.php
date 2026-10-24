<div @if ($poll) wire:poll.60s.visible @endif x-data="{ open: false }" x-on:keydown.escape.stop="open = false; $refs.trigger.focus()" class="relative">
    <button type="button" x-ref="trigger" x-on:click="open = !open; open && ! @js($poll) && $wire.$refresh()" :aria-expanded="open" aria-haspopup="true"
            class="relative rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-fg">
        <x-icon name="bell" class="size-[1.125rem]" />
        <span class="sr-only">{{ trans_choice('notifications.unread_count', $unread, ['count' => $unread]) }}</span>
        @if ($unread > 0)
            <span class="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[0.625rem] leading-4 font-semibold text-white tabular-nums" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-anchor.bottom-end.offset.6="$refs.trigger" x-on:click.outside="open = false"
         x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         class="z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-line bg-surface shadow-lg" role="dialog" aria-label="{{ __('notifications.title') }}">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-fg">{{ __('notifications.title') }}</p>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-primary hover:underline">{{ __('notifications.mark_all') }}</button>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-line overflow-y-auto scroll-thin">
            @forelse ($latest as $notification)
                <li wire:key="n-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-sunken/70">
                        <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full', 'bg-primary-soft text-primary' => ! $notification->read_at, 'bg-surface-sunken text-subtle' => $notification->read_at])>
                            <x-icon :name="$notification->data['icon'] ?? 'bell'" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-sm', 'font-semibold text-fg' => ! $notification->read_at, 'text-muted' => $notification->read_at])>{{ $notification->data['title'] ?? '' }}</span>
                            @if (! empty($notification->data['body'])) <span class="block truncate text-xs text-muted">{{ $notification->data['body'] }}</span> @endif
                            <span class="mt-0.5 block text-[0.6875rem] text-subtle">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at) <span class="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span> @endunless
                    </button>
                </li>
            @empty
                <li><x-ui.empty-state compact icon="bell" :title="__('notifications.none')" /></li>
            @endforelse
        </ul>
        <a href="{{ route('workspace.notifications') }}" class="block border-t border-line px-4 py-2.5 text-center text-[0.8125rem] font-medium text-primary hover:bg-surface-sunken/70">{{ __('notifications.view_all') }}</a>
    </div>
</div>
