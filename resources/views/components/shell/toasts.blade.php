{{-- Toast region. Fed by the Alpine "toasts" store, Livewire `toast` events and session flashes. --}}
@php($flash = collect(['success', 'error', 'warning', 'info'])->mapWithKeys(fn ($type) => [$type => session($type)])->filter())
<div x-data x-init="@foreach ($flash as $type => $message) $store.toasts.push({ type: @js($type === 'error' ? 'danger' : $type), title: @js($message) }); @endforeach"
     class="pointer-events-none fixed inset-x-0 top-0 z-[70] flex flex-col items-center gap-2 p-4 sm:top-auto sm:bottom-0 sm:items-end sm:p-6"
     aria-live="polite" aria-atomic="false">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter="transition duration-200 ease-[var(--ease-out-soft)]" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-surface-raised p-4 shadow-lg" role="status">
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full"
                  :class="{ 'bg-success-soft text-success': toast.type === 'success', 'bg-danger-soft text-danger': toast.type === 'danger', 'bg-warning-soft text-warning': toast.type === 'warning', 'bg-info-soft text-info': toast.type === 'info' }">
                <x-icon name="check" class="size-3.5" x-show="toast.type === 'success'" />
                <x-icon name="x" class="size-3.5" x-show="toast.type === 'danger'" />
                <x-icon name="triangle-alert" class="size-3" x-show="toast.type === 'warning'" />
                <x-icon name="info" class="size-3.5" x-show="toast.type === 'info'" />
            </span>
            <div class="min-w-0 flex-1 text-sm">
                <p class="font-semibold text-fg" x-text="toast.title"></p>
                <p class="mt-0.5 text-muted" x-show="toast.message" x-text="toast.message"></p>
            </div>
            <button type="button" class="-m-1 rounded p-1 text-subtle hover:text-fg" x-on:click="$store.toasts.dismiss(toast.id)">
                <x-icon name="x" class="size-4" /><span class="sr-only">{{ __('ui.dismiss') }}</span>
            </button>
        </div>
    </template>
</div>
