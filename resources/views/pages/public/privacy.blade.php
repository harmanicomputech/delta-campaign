<x-layouts::public :title="__('public.nav_privacy')" :description="__('public.privacy_intro')">
    <x-public.band :title="$notice?->title ?? __('public.nav_privacy')" :eyebrow="__('public.your_data')" :description="__('public.privacy_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        @if ($notice)
            @if (str_starts_with($notice->version, 'PLACEHOLDER')) <x-ui.alert tone="warning" class="mb-6">{{ __('public.privacy_placeholder') }}</x-ui.alert> @endif
            <div class="mb-8 rounded-xl bg-primary-soft/60 p-5 text-[0.9375rem] leading-7 text-fg">{{ $notice->summary(app()->getLocale()) }}</div>
            <x-public.markdown :text="$notice->body(app()->getLocale())" />
            <p class="mt-10 text-xs text-subtle">{{ __('public.notice_version', ['version' => $notice->version, 'date' => $notice->published_at?->timezone(config('campaign.display_timezone'))->format('j F Y')]) }}</p>
        @else
            <x-public.placeholder />
        @endif
        <div class="mt-10 rounded-xl border border-line bg-surface p-5">
            <p class="font-semibold text-fg">{{ __('public.rights_title') }}</p>
            <p class="mt-1 text-sm text-muted">{{ __('public.rights_body') }}</p>
            <a href="{{ route('public.preferences') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">{{ __('public.nav_preferences') }} <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
</x-layouts::public>
