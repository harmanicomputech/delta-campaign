{{--
    Workspace shell: collapsible sidebar (desktop), drawer + bottom bar (mobile),
    top bar with breadcrumbs, search, connectivity, language and theme.
--}}
@props(['title' => null, 'breadcrumbs' => [], 'home' => null])
@php
    $navigation = app(\App\Support\Navigation\Navigation::class);
    $groups = $navigation->groups();
    $home ??= auth()->check() ? route('workspace.dashboard') : route('design-system.shell');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <x-shell.head :title="$title" />
</head>
<body x-data class="min-h-dvh">
    <a href="#main" class="sr-only z-[80] rounded-md bg-primary px-4 py-2 text-on-primary focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('ui.skip_to_content') }}</a>

    <x-shell.sidebar :groups="$groups" :home="$home" />
    <x-shell.mobile-drawer :groups="$groups" />

    <div class="flex min-h-dvh flex-col transition-[padding] duration-300 ease-[var(--ease-out-soft)] lg:pl-64 sidebar-collapsed:lg:pl-[4.5rem]">
        <header class="sticky top-0 z-20 border-b border-line bg-canvas/85 backdrop-blur-md supports-[backdrop-filter]:bg-canvas/70">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" class="-ml-1.5 rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-fg lg:hidden" x-on:click="$store.sidebar.mobileOpen = true">
                    <x-icon name="menu" class="size-5" /><span class="sr-only">{{ __('ui.open_navigation') }}</span>
                </button>
                <a href="{{ $home }}" class="min-w-0 lg:hidden" aria-label="{{ config('app.name') }}"><x-shell.brand compact /></a>

                <div class="hidden min-w-0 flex-1 lg:block">
                    @if ($breadcrumbs) <x-ui.breadcrumbs :items="$breadcrumbs" /> @endif
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1.5">
                    <button type="button" x-on:click="$dispatch('open-command-palette')"
                            class="hidden h-9 w-64 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-[0.8125rem] text-subtle shadow-xs transition-colors hover:border-line-strong hover:text-muted md:flex xl:w-72">
                        <x-icon name="search" class="size-4" />
                        <span class="flex-1 text-left">{{ __('ui.search') }}…</span>
                        <x-ui.kbd>Ctrl</x-ui.kbd><x-ui.kbd>K</x-ui.kbd>
                    </button>
                    <button type="button" x-on:click="$dispatch('open-command-palette')" class="rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-fg md:hidden">
                        <x-icon name="search" class="size-[1.125rem]" /><span class="sr-only">{{ __('ui.search') }}</span>
                    </button>

                    @unless (app()->isProduction())
                        <x-ui.badge tone="accent" size="sm" class="mx-1 hidden sm:inline-flex">{{ __('shell.environment.'.app()->environment()) }}</x-ui.badge>
                    @endunless

                    <x-shell.sync-status />

                    @auth <livewire:workspace.notifications.notification-bell :poll="! \App\Support\LowData::enabled()" /> @endauth
                    <x-shell.locale-switcher />
                    <x-shell.theme-switcher />
                </div>
            </div>
            <div x-show="!$store.network.online" x-cloak x-transition class="flex items-center gap-2 border-t border-warning/25 bg-warning-soft px-4 py-2 text-[0.8125rem] font-medium text-warning sm:px-6 lg:px-8" role="status">
                <x-icon name="wifi-off" class="size-4" /> {{ __('ui.offline') }}
            </div>
        </header>

        <main id="main" tabindex="-1" class="mx-auto w-full max-w-[1440px] flex-1 px-4 pt-6 pb-28 outline-none sm:px-6 lg:px-8 lg:pt-8 lg:pb-12">
            @if ($breadcrumbs) <x-ui.breadcrumbs :items="$breadcrumbs" class="mb-4 lg:hidden" /> @endif
            {{ $slot }}
        </main>
    </div>

    <x-shell.mobile-bar :items="$navigation->mobile()" />
    <x-shell.command-palette :items="$navigation->paletteItems()" />
    <x-shell.toasts />
    @livewireScriptConfig
</body>
</html>
