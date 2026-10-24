@props(['groups'])
<div x-show="$store.sidebar.mobileOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('ui.main_navigation') }}"
     x-on:keydown.escape.window="$store.sidebar.mobileOpen = false">
    <div x-show="$store.sidebar.mobileOpen" x-transition.opacity class="absolute inset-0 bg-ink-950/60" x-on:click="$store.sidebar.mobileOpen = false" aria-hidden="true"></div>
    <div x-show="$store.sidebar.mobileOpen" x-trap.inert.noscroll="$store.sidebar.mobileOpen"
         x-transition:enter="transform transition duration-300 ease-[var(--ease-out-soft)]" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
         class="absolute inset-y-0 left-0 flex w-[85%] max-w-xs flex-col bg-nav shadow-lg">
        <div class="flex h-16 items-center justify-between px-4">
            <x-shell.brand inverse />
            <button type="button" class="rounded-lg p-2 text-nav-muted hover:bg-nav-hover hover:text-white" x-on:click="$store.sidebar.mobileOpen = false">
                <x-icon name="x" class="size-5" /><span class="sr-only">{{ __('ui.close_navigation') }}</span>
            </button>
        </div>
        <div class="nav-scroll flex-1 overflow-y-auto px-3 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <x-shell.nav-links :groups="$groups" />
        </div>
    </div>
</div>
