@props(['event'])
@php($start = $event->starts_at->timezone(config('campaign.display_timezone')))
<a href="{{ route('public.events.show', $event->slug) }}" {{ $attributes->class('group flex gap-4 rounded-xl border border-line bg-surface p-4 shadow-xs transition hover:border-line-strong hover:shadow-md') }}>
    <span class="flex w-14 shrink-0 flex-col items-center justify-center rounded-lg bg-primary-soft py-2 text-primary-soft-fg">
        <span class="text-[0.6875rem] font-semibold uppercase">{{ $start->format('M') }}</span>
        <span class="font-display text-2xl leading-none font-semibold">{{ $start->format('j') }}</span>
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-1.5">
            @if ($event->status->value === 'cancelled') <x-ui.badge tone="danger" size="sm">{{ __('events.cancelled') }}</x-ui.badge> @endif
            @if ($event->is_sample) <x-ui.sample-badge /> @endif
        </span>
        <span class="block font-semibold text-fg group-hover:text-primary">{{ $event->translated('title') }}</span>
        <span class="mt-0.5 block text-sm text-muted">{{ $start->format('D j M, g:ia') }} · {{ $event->venue }}@if ($event->lga) · {{ $event->lga->name }}@endif</span>
    </span>
</a>
