@php($candidate = config('campaign.public.candidate_name'))
<x-layouts::public :hero="true" :title="config('app.name')" :json-ld="['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('app.name'), 'url' => route('home')]">
    <section class="relative isolate overflow-hidden bg-nav">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(80%_70%_at_85%_10%,rgb(35_145_95/0.55),transparent_65%),radial-gradient(60%_60%_at_5%_100%,rgb(226_165_47/0.2),transparent_60%)]" aria-hidden="true"></div>
        <svg class="absolute inset-0 -z-10 size-full opacity-[0.1]" aria-hidden="true">
            <defs><pattern id="home-grid" width="56" height="56" patternUnits="userSpaceOnUse"><path d="M56 0H0v56" fill="none" stroke="white" stroke-width="0.6" /></pattern></defs>
            <rect width="100%" height="100%" fill="url(#home-grid)" />
        </svg>
        <div class="mx-auto max-w-6xl px-5 pt-32 pb-20 sm:px-8 sm:pt-40 sm:pb-28">
            <p class="text-xs font-semibold tracking-[0.16em] text-accent-300 uppercase">{{ config('campaign.public.office') }} · Delta State</p>
            <h1 class="mt-5 max-w-3xl font-display text-[2.5rem] leading-[1.08] font-medium tracking-tight text-white sm:text-6xl">{{ $candidate }}</h1>
            <p class="mt-6 max-w-2xl text-base leading-7 text-nav-fg/85 sm:text-lg sm:leading-8">{{ config('campaign.public.tagline') }}</p>
            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('public.join') }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-accent-400 px-6 text-[0.9375rem] font-semibold text-ink-900 shadow-sm transition hover:bg-accent-300">{{ __('public.cta_join') }} <x-icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('public.volunteer') }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/25 px-6 text-[0.9375rem] font-semibold text-white transition hover:bg-white/10">{{ __('public.cta_volunteer') }}</a>
            </div>
        </div>
    </section>

    <section class="relative z-10 mx-auto -mt-8 max-w-6xl px-5 sm:px-8">
        <div class="grid gap-3 rounded-2xl border border-line bg-surface p-3 shadow-lg sm:grid-cols-3">
            @foreach ([['public.polling-unit', 'map-pinned', __('public.quick_pu'), __('public.quick_pu_help')], ['public.events.index', 'calendar-days', __('public.quick_events'), __('public.quick_events_help')], ['public.preferences', 'lock-keyhole', __('public.quick_prefs'), __('public.quick_prefs_help')]] as [$route, $icon, $label, $help])
                <a href="{{ route($route) }}" class="group flex items-start gap-3 rounded-xl p-3 transition hover:bg-surface-sunken">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-5" /></span>
                    <span><span class="block font-semibold text-fg group-hover:text-primary">{{ $label }}</span><span class="block text-[0.8125rem] text-muted">{{ $help }}</span></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <div class="flex items-end justify-between gap-4">
            <div><p class="text-xs font-semibold tracking-[0.14em] text-primary uppercase">{{ __('public.nav_news') }}</p><h2 class="mt-1 font-display text-2xl font-semibold tracking-tight text-fg sm:text-3xl">{{ __('public.latest_news') }}</h2></div>
            <a href="{{ route('public.news.index') }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">{{ __('public.see_all') }}</a>
        </div>
        @if ($news->isEmpty())
            <x-public.placeholder class="mt-6" :title="__('public.no_news')" />
        @else
            <div class="mt-6 grid gap-5 md:grid-cols-3 [&>*]:min-w-0">@foreach ($news as $item) <x-public.article-card :item="$item" /> @endforeach</div>
        @endif
    </section>

    <section class="border-y border-line bg-surface">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-2 [&>*]:min-w-0">
            <div>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-display text-2xl font-semibold tracking-tight text-fg">{{ __('public.upcoming_events') }}</h2>
                    <a href="{{ route('public.events.index') }}" class="text-sm font-semibold text-primary hover:underline">{{ __('public.see_all') }}</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($events as $event) <x-public.event-card :event="$event" /> @empty <x-public.placeholder :title="__('public.no_events')">{{ __('public.no_events_help') }}</x-public.placeholder> @endforelse
                </div>
            </div>
            <div>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-display text-2xl font-semibold tracking-tight text-fg">{{ __('public.fact_checks') }}</h2>
                    <a href="{{ route('public.fact-checks.index') }}" class="text-sm font-semibold text-primary hover:underline">{{ __('public.see_all') }}</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($factChecks as $item)
                        <a href="{{ $item->publicUrl() }}" class="block rounded-xl border border-line p-4 transition hover:border-line-strong">
                            <span class="block font-semibold text-fg">{{ $item->live('title') }}</span>
                            @if ($item->live('summary')) <span class="mt-1 block text-sm text-muted">{{ $item->live('summary') }}</span> @endif
                        </a>
                    @empty
                        <x-public.placeholder :title="__('public.no_fact_checks')" />
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <div class="flex items-end justify-between gap-4">
            <div><p class="text-xs font-semibold tracking-[0.14em] text-primary uppercase">{{ __('public.nav_projects') }}</p><h2 class="mt-1 font-display text-2xl font-semibold tracking-tight text-fg sm:text-3xl">{{ __('public.project_directory') }}</h2></div>
            <a href="{{ route('public.projects.index') }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">{{ __('public.see_all') }}</a>
        </div>
        @if ($projects->isEmpty())
            <x-public.placeholder class="mt-6" :title="__('public.no_projects')">{{ __('public.no_projects_help') }}</x-public.placeholder>
        @else
            <div class="mt-6 grid gap-5 md:grid-cols-3 [&>*]:min-w-0">@foreach ($projects as $project) <x-public.project-card :project="$project" /> @endforeach</div>
        @endif
    </section>

    <section class="bg-nav">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-14 sm:px-8 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ __('public.cta_band_title') }}</h2>
                <p class="mt-2 max-w-xl text-nav-fg/80">{{ __('public.cta_band_body') }}</p>
            </div>
            <a href="{{ route('public.join') }}" class="inline-flex min-h-12 shrink-0 items-center gap-2 self-start rounded-xl bg-accent-400 px-6 font-semibold text-ink-900 hover:bg-accent-300 md:self-auto">{{ __('public.cta_join') }} <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </section>
</x-layouts::public>
