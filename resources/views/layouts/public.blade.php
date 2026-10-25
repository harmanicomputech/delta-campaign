{{--
    Public website layout: sticky header with navigation and language switch,
    SEO and social metadata, footer with privacy links. Server-rendered and light.
--}}
@props(['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'canonical' => null, 'hero' => false, 'jsonLd' => null])
@php
    $description ??= __('public.meta_description');
    $canonical ??= url()->current();
    $imageUrl = $image ?? asset('icons/icon-512.png');
    $nav = [
        ['route' => 'public.about', 'label' => __('public.nav_about')],
        ['route' => 'public.projects.index', 'label' => __('public.nav_projects')],
        ['route' => 'public.news.index', 'label' => __('public.nav_news')],
        ['route' => 'public.events.index', 'label' => __('public.nav_events')],
        ['route' => 'public.fact-checks.index', 'label' => __('public.nav_fact_checks')],
        ['route' => 'public.lgas.index', 'label' => __('public.nav_lgas')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'pcm' ? 'pcm' : 'en' }}" data-theme="light">
<head>
    <x-shell.head :title="$title" :description="$description" robots="index, follow">
        <link rel="canonical" href="{{ $canonical }}">
        <link rel="alternate" type="application/rss+xml" title="{{ __('public.nav_news') }}" href="{{ route('public.news.feed') }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $imageUrl }}">
        <meta property="og:locale" content="{{ app()->getLocale() === 'pcm' ? 'pcm_NG' : 'en_NG' }}">
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $imageUrl }}">
        @if ($jsonLd)
            <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
    </x-shell.head>
</head>
<body x-data="{ menu: false }" class="flex min-h-dvh flex-col bg-canvas text-fg">
    <a href="#main" class="sr-only z-[80] rounded-md bg-primary px-4 py-2 text-on-primary focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('ui.skip_to_content') }}</a>

    <header @class(['z-30', 'absolute inset-x-0 top-0' => $hero, 'sticky top-0 border-b border-line bg-canvas/90 backdrop-blur-md' => ! $hero])>
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-5 sm:h-20 sm:px-8">
            <a href="{{ route('home') }}" class="shrink-0 rounded-lg"><x-shell.brand :inverse="$hero" :subtitle="false" /></a>
            <nav aria-label="{{ __('public.main_navigation') }}" class="ml-6 hidden items-center gap-1 lg:flex">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['route'], str_replace('.index', '.*', $item['route']))) aria-current="page" @endif
                       @class(['rounded-md px-3 py-2 text-sm font-medium transition-colors',
                           'text-white/85 hover:bg-white/10 hover:text-white' => $hero,
                           'text-muted hover:bg-surface-sunken hover:text-fg aria-[current=page]:text-primary' => ! $hero])>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <x-shell.locale-switcher :inverse="$hero" />
                <a href="{{ route('public.join') }}" class="hidden rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-900 shadow-sm transition hover:bg-accent-300 sm:inline-flex">{{ __('public.cta_join') }}</a>
                <button type="button" x-on:click="menu = !menu" :aria-expanded="menu" aria-controls="public-menu" @class(['rounded-md p-2 lg:hidden', 'text-white' => $hero, 'text-fg' => ! $hero])>
                    <x-icon name="menu" class="size-5" /><span class="sr-only">{{ __('ui.open_navigation') }}</span>
                </button>
            </div>
        </div>
        <nav id="public-menu" x-show="menu" x-cloak x-transition.opacity aria-label="{{ __('public.main_navigation') }}" class="border-t border-line bg-surface px-5 py-3 shadow-lg lg:hidden">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" class="block rounded-md px-3 py-2.5 text-[0.9375rem] font-medium text-fg hover:bg-surface-sunken">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('public.join') }}" class="mt-2 block rounded-lg bg-primary px-3 py-2.5 text-center text-[0.9375rem] font-semibold text-on-primary">{{ __('public.cta_join') }}</a>
        </nav>
    </header>

    <main id="main" class="flex-1">{{ $slot }}</main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
            <div class="space-y-3 lg:col-span-1">
                <x-shell.brand :subtitle="false" />
                <p class="text-[0.8125rem] leading-6 text-muted">{{ __('public.footer_note') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('public.footer_campaign') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.about') }}">{{ __('public.nav_about') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.manifesto') }}">{{ __('public.nav_manifesto') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.projects.index') }}">{{ __('public.nav_projects') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.lgas.index') }}">{{ __('public.nav_lgas') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('public.footer_take_part') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.join') }}">{{ __('public.nav_register') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.volunteer') }}">{{ __('public.nav_volunteer') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.polling-unit') }}">{{ __('public.nav_polling_unit') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.contact') }}">{{ __('public.nav_contact') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('public.footer_your_data') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.privacy') }}">{{ __('public.nav_privacy') }}</a></li>
                    <li><a class="text-muted hover:text-primary" href="{{ route('public.preferences') }}">{{ __('public.nav_preferences') }}</a></li>
                </ul>
                <p class="mt-4 text-xs text-subtle">{{ __('public.footer_stop') }}</p>
            </div>
        </div>
        <div class="border-t border-line">
            <p class="mx-auto max-w-6xl px-5 py-5 text-xs text-subtle sm:px-8">© {{ now()->year }} {{ config('app.name') }} · {{ __('public.footer_official') }}</p>
        </div>
    </footer>
    <x-shell.toasts />
    @livewireScriptConfig
</body>
</html>
