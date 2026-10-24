@props(['groups', 'home'])
@php($tight = 'sidebar-collapsed:justify-center sidebar-collapsed:px-0')
<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-nav-line bg-nav transition-[width] duration-300 ease-[var(--ease-out-soft)] sidebar-collapsed:w-[4.5rem] lg:flex">
    <div class="flex h-16 shrink-0 items-center px-4 {{ $tight }}">
        <a href="{{ $home }}" class="rounded-lg"><x-shell.brand inverse collapsible /></a>
    </div>

    <div class="nav-scroll flex-1 overflow-y-auto overflow-x-hidden px-3 pt-2 pb-6">
        <x-shell.nav-links :groups="$groups" collapsible />
    </div>

    <div class="shrink-0 space-y-2 border-t border-nav-line p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-1.5 {{ $tight }}">
            <x-ui.avatar :name="__('shell.preview_user')" size="sm" class="ring-nav" />
            <div class="min-w-0 flex-1 leading-tight sidebar-collapsed:hidden">
                <p class="truncate text-[0.8125rem] font-medium text-white">{{ __('shell.preview_user') }}</p>
                <p class="truncate text-[0.6875rem] text-nav-muted">{{ __('shell.preview_role') }}</p>
            </div>
        </div>
        <button type="button" x-on:click="$store.sidebar.toggle()"
                class="flex h-9 w-full items-center gap-3 rounded-lg px-3 text-[0.8125rem] text-nav-muted transition-colors hover:bg-nav-hover hover:text-nav-fg {{ $tight }}"
                :aria-expanded="(!$store.sidebar.collapsed).toString()">
            <x-icon name="panel-left-close" class="size-[1.125rem] sidebar-collapsed:hidden" />
            <x-icon name="panel-left-open" class="hidden size-[1.125rem] sidebar-collapsed:block" />
            <span class="sidebar-collapsed:sr-only">{{ __('ui.collapse_sidebar') }}</span>
        </button>
    </div>
</aside>
