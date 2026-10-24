{{-- Public holding page. Replaced by the full public website in Phase 10. --}}
<x-layouts::public :description="__('public.holding_body')">
    <section class="relative isolate overflow-hidden bg-nav">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(80%_70%_at_85%_10%,rgb(35_145_95/0.55),transparent_65%),radial-gradient(60%_60%_at_5%_100%,rgb(226_165_47/0.2),transparent_60%)]" aria-hidden="true"></div>
        <svg class="absolute inset-0 -z-10 size-full opacity-[0.1]" aria-hidden="true">
            <defs><pattern id="home-grid" width="56" height="56" patternUnits="userSpaceOnUse"><path d="M56 0H0v56" fill="none" stroke="white" stroke-width="0.6" /></pattern></defs>
            <rect width="100%" height="100%" fill="url(#home-grid)" />
        </svg>

        <div class="mx-auto max-w-6xl px-5 pt-36 pb-24 sm:px-8 sm:pt-44 sm:pb-32">
            <p class="text-xs font-semibold tracking-[0.16em] text-accent-300 uppercase">{{ __('public.holding_eyebrow') }}</p>
            <h1 class="mt-5 max-w-3xl font-display text-[2.5rem] leading-[1.08] font-medium tracking-tight text-white sm:text-6xl">{{ __('public.holding_title') }}</h1>
            <p class="mt-6 max-w-2xl text-base leading-7 text-nav-fg/85 sm:text-lg sm:leading-8">{{ __('public.holding_body') }}</p>

            <div class="mt-12 max-w-xl rounded-xl border border-dashed border-white/25 bg-white/[0.03] px-5 py-4 text-sm text-nav-fg/70">
                {{ __('public.placeholder_candidate') }}
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <p class="flex items-center gap-2 text-[0.8125rem] text-muted">
            <x-icon name="lock-keyhole" class="size-4 text-primary" /> {{ __('public.holding_privacy') }}
        </p>
    </section>
</x-layouts::public>
