@php($tz = config('campaign.display_timezone'))
@php($isFact = $item->type->value === 'fact_check')
<x-layouts::public :title="$item->live('title')" :description="$item->live('summary')" :image="$item->cover?->url(1600)" type="article"
    :json-ld="['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $item->live('title'), 'datePublished' => $item->published_at?->toAtomString(), 'dateModified' => $item->updated_at->toAtomString(), 'publisher' => ['@type' => 'Organization', 'name' => config('app.name')]]">
    <article>
        <x-public.band :title="$item->live('title')" :eyebrow="$isFact ? __('public.fact_check') : __('public.nav_news')" :description="$item->live('summary')">
            <p class="mt-4 text-sm text-subtle">{{ __('public.published_on', ['date' => $item->published_at->timezone($tz)->format('j F Y')]) }}@if ($item->is_sample) <x-ui.sample-badge class="ml-1" /> @endif</p>
        </x-public.band>
        <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
            @if ($item->cover) <img src="{{ $item->cover->url(960) }}" srcset="{{ $item->cover->srcset() }}" sizes="(min-width: 768px) 720px, 100vw" alt="{{ $item->cover->alt() }}" class="mb-8 w-full rounded-xl" decoding="async"> @endif
            <x-public.markdown :text="$item->live('body')" />
        </div>
    </article>
    @if ($more->isNotEmpty())
        <section class="border-t border-line bg-surface">
            <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
                <h2 class="font-display text-xl font-semibold text-fg">{{ __('public.more') }}</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-3 [&>*]:min-w-0">@foreach ($more as $other) <x-public.article-card :item="$other" /> @endforeach</div>
            </div>
        </section>
    @endif
</x-layouts::public>
