@props(['item'])
<a href="{{ $item->publicUrl() }}" {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs transition hover:-translate-y-0.5 hover:shadow-md') }}>
    @if ($item->cover)
        <img src="{{ $item->cover->url(480) }}" srcset="{{ $item->cover->srcset() }}" sizes="(min-width: 1024px) 360px, 100vw" alt="{{ $item->cover->alt() }}" loading="lazy" decoding="async" class="aspect-[16/9] w-full object-cover">
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-medium text-subtle">
            {{ $item->published_at?->timezone(config('campaign.display_timezone'))->format('j M Y') }}
            @if ($item->is_sample) <x-ui.sample-badge class="ml-1" /> @endif
        </p>
        <h3 class="mt-1.5 font-display text-lg leading-snug font-semibold text-fg group-hover:text-primary">{{ $item->live('title') }}</h3>
        @if ($item->live('summary')) <p class="mt-2 line-clamp-3 text-sm text-muted">{{ $item->live('summary') }}</p> @endif
    </div>
</a>
