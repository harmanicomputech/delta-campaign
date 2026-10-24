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
        @auth
            @php($primary = auth()->user()->loadedAssignments()->sortBy(fn ($a) => $a->scope_type->rank())->first())
            <x-ui.dropdown align="top-start" width="w-60" :label="__('shell.account_menu')" class="w-full">
                <x-slot:trigger class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left hover:bg-nav-hover {{ $tight }}">
                    <x-ui.avatar :name="auth()->user()->name" size="sm" class="ring-nav" />
                    <span class="min-w-0 flex-1 leading-tight sidebar-collapsed:hidden">
                        <span class="block truncate text-[0.8125rem] font-medium text-white">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-[0.6875rem] text-nav-muted">{{ $primary?->role->name ?? __('shell.no_role') }}</span>
                    </span>
                    <x-icon name="chevrons-up-down" class="size-4 text-nav-muted sidebar-collapsed:hidden" />
                </x-slot:trigger>
                <x-ui.dropdown-item icon="shield-check" :href="route('workspace.account')">{{ __('shell.account_security') }}</x-ui.dropdown-item>
                <form method="POST" action="{{ route('logout') }}" x-data x-on:submit.prevent="$store.sync.forgetLists().finally(() => $el.submit())">
                    @csrf
                    <x-ui.dropdown-item icon="log-out" type="submit">{{ __('shell.sign_out') }}</x-ui.dropdown-item>
                </form>
            </x-ui.dropdown>
        @else
            <div class="flex items-center gap-3 rounded-lg px-2 py-1.5 {{ $tight }}">
                <x-ui.avatar :name="__('shell.preview_user')" size="sm" class="ring-nav" />
                <div class="min-w-0 flex-1 leading-tight sidebar-collapsed:hidden">
                    <p class="truncate text-[0.8125rem] font-medium text-white">{{ __('shell.preview_user') }}</p>
                    <p class="truncate text-[0.6875rem] text-nav-muted">{{ __('shell.preview_role') }}</p>
                </div>
            </div>
        @endauth
        <button type="button" x-on:click="$store.sidebar.toggle()"
                class="flex h-9 w-full items-center gap-3 rounded-lg px-3 text-[0.8125rem] text-nav-muted transition-colors hover:bg-nav-hover hover:text-nav-fg {{ $tight }}"
                :aria-expanded="(!$store.sidebar.collapsed).toString()">
            <x-icon name="panel-left-close" class="size-[1.125rem] sidebar-collapsed:hidden" />
            <x-icon name="panel-left-open" class="hidden size-[1.125rem] sidebar-collapsed:block" />
            <span class="sidebar-collapsed:sr-only">{{ __('ui.collapse_sidebar') }}</span>
        </button>
    </div>
</aside>
