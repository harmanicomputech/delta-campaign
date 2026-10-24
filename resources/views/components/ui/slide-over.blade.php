{{-- Contextual side panel for record details. Same events as <x-ui.modal>. --}}
@props(['name', 'title' => null, 'description' => null, 'width' => 'md'])
@php($widths = ['md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl'])
<div
    x-data="{ open: false, name: @js($name) }"
    x-on:open-modal.window="(($event.detail?.name ?? $event.detail) === name) && (open = true)"
    x-on:close-modal.window="(($event.detail?.name ?? $event.detail) === name) && (open = false)"
    {{ $attributes }}
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-hidden" x-on:keydown.escape.prevent.stop="open = false"
             role="dialog" aria-modal="true" aria-labelledby="slide-{{ $name }}-title">
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-ink-950/40" x-on:click="open = false" aria-hidden="true"></div>
            <div x-show="open" x-trap.inert.noscroll="open"
                 x-transition:enter="transform transition duration-300 ease-[var(--ease-out-soft)]" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute inset-y-0 right-0 flex w-full flex-col border-l border-line bg-surface-raised shadow-lg {{ $widths[$width] ?? $widths['md'] }}">
                <header class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
                    <div class="min-w-0">
                        <h2 id="slide-{{ $name }}-title" class="text-base font-semibold text-fg">{{ $title }}</h2>
                        @if ($description) <p class="mt-0.5 text-sm text-muted">{{ $description }}</p> @endif
                    </div>
                    <button type="button" class="-mr-2 rounded-md p-1.5 text-subtle hover:bg-surface-sunken hover:text-fg" x-on:click="open = false">
                        <x-icon name="x" /><span class="sr-only">{{ __('ui.close') }}</span>
                    </button>
                </header>
                <div class="scroll-thin flex-1 overflow-y-auto px-6 py-5">{{ $slot }}</div>
                @isset($footer)
                    <footer class="flex justify-end gap-2 border-t border-line px-6 py-4">{{ $footer }}</footer>
                @endisset
            </div>
        </div>
    </template>
</div>
