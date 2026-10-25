@php($title = $page?->live('title') ?? __('public.page_'.$slug))
<x-layouts::public :title="$title" :description="$page?->live('summary')" :image="$page?->cover?->url(1600)" type="article">
    <x-public.band :title="$title" :eyebrow="config('campaign.public.candidate_name')" :description="$page?->live('summary')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        @if ($page)
            @if ($page->is_sample) <x-ui.alert tone="warning" class="mb-6">{{ __('public.sample_content') }}</x-ui.alert> @endif
            @if ($page->cover) <img src="{{ $page->cover->url(960) }}" srcset="{{ $page->cover->srcset() }}" sizes="(min-width: 768px) 720px, 100vw" alt="{{ $page->cover->alt() }}" class="mb-8 w-full rounded-xl" decoding="async"> @endif
            <x-public.markdown :text="$page->live('body')" />
            <p class="mt-10 text-xs text-subtle">{{ __('public.updated', ['date' => $page->published_at->timezone(config('campaign.display_timezone'))->format('j F Y')]) }}</p>
        @else
            <x-public.placeholder />
        @endif
    </div>
</x-layouts::public>
