{{--
    Accessible dialog. Open with $dispatch('open-modal', 'name') (Alpine) or
    $this->dispatch('open-modal', name: 'name') (Livewire); close with 'close-modal'.
    Focus is trapped while open and returned to the trigger on close.
--}}
@props(['name', 'title' => null, 'description' => null, 'maxWidth' => 'lg', 'show' => false, 'icon' => null, 'tone' => 'brand'])
@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
    $iconTones = ['brand' => 'bg-primary-soft text-primary', 'danger' => 'bg-danger-soft text-danger', 'warning' => 'bg-warning-soft text-warning'];
@endphp
<div
    x-data="{ open: @js($show), name: @js($name) }"
    x-on:open-modal.window="(($event.detail?.name ?? $event.detail) === name) && (open = true)"
    x-on:close-modal.window="(($event.detail?.name ?? $event.detail) === name) && (open = false)"
    {{ $attributes }}
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
             x-on:keydown.escape.prevent.stop="open = false"
             role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}-title" @if ($description) aria-describedby="modal-{{ $name }}-desc" @endif>
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-ink-950/50 backdrop-blur-[2px]" x-on:click="open = false" aria-hidden="true"></div>

            <div x-show="open" x-trap.inert.noscroll="open"
                 x-transition:enter="transition duration-200 ease-[var(--ease-out-soft)]"
                 x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[0.97]"
                 x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                 x-transition:leave="transition duration-150 ease-in"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="translate-y-2 opacity-0 sm:translate-y-0 sm:scale-[0.98]"
                 class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-2xl border border-line bg-surface-raised shadow-lg sm:rounded-2xl {{ $widths[$maxWidth] ?? $widths['lg'] }}">
                <div class="flex items-start gap-4 px-6 pt-6">
                    @if ($icon)
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $iconTones[$tone] ?? $iconTones['brand'] }}">
                            <x-icon :name="$icon" class="size-5" />
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h2 id="modal-{{ $name }}-title" class="text-base font-semibold text-fg">{{ $title }}</h2>
                        @if ($description) <p id="modal-{{ $name }}-desc" class="mt-1 text-sm text-muted">{{ $description }}</p> @endif
                    </div>
                    <button type="button" class="-mt-1 -mr-2 rounded-md p-1.5 text-subtle hover:bg-surface-sunken hover:text-fg" x-on:click="open = false">
                        <x-icon name="x" /><span class="sr-only">{{ __('ui.close') }}</span>
                    </button>
                </div>
                <div class="scroll-thin overflow-y-auto px-6 py-5 text-sm text-muted">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 border-t border-line bg-surface-sunken/40 px-6 py-4 sm:flex-row sm:justify-end">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
