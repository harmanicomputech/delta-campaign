@php($start = $event->starts_at->timezone(config('campaign.display_timezone')))
<x-layouts::public :title="$event->translated('title')" :description="\Illuminate\Support\Str::limit(strip_tags($event->translated('description')), 155)" :image="$event->cover?->url(1600)"
    :json-ld="['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $event->translated('title'), 'startDate' => $event->starts_at->toAtomString(), 'endDate' => $event->ends_at?->toAtomString(), 'eventStatus' => $event->status->value === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled', 'location' => ['@type' => 'Place', 'name' => $event->venue, 'address' => trim(($event->lga?->name ?? '').', Delta State', ', ')]]">
    <x-public.band :title="$event->translated('title')" :eyebrow="$start->format('l j F Y · g:ia')">
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.badge icon="map-pinned">{{ $event->venue }}@if ($event->lga) · {{ $event->lga->name }}@endif</x-ui.badge>
            @if ($event->status->value === 'cancelled') <x-ui.badge tone="danger">{{ __('events.cancelled') }}</x-ui.badge> @endif
            @if ($event->is_sample) <x-ui.sample-badge /> @endif
        </div>
    </x-public.band>
    <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div>
            @if ($event->status->value === 'cancelled') <x-ui.alert tone="danger" class="mb-6" :title="__('events.cancelled')">{{ $event->cancellation_reason }}</x-ui.alert> @endif
            @if ($event->cover) <img src="{{ $event->cover->url(960) }}" srcset="{{ $event->cover->srcset() }}" sizes="(min-width: 1024px) 720px, 100vw" alt="{{ $event->cover->alt() }}" class="mb-8 w-full rounded-xl" decoding="async"> @endif
            <x-public.markdown :text="$event->translated('description')" />
        </div>
        <aside>
            <livewire:public-site.event-signup :event="$event" />
        </aside>
    </div>
</x-layouts::public>
