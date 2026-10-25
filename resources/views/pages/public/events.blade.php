<x-layouts::public :title="__('public.nav_events')" :description="__('public.events_intro')">
    <x-public.band :title="__('public.nav_events')" :description="__('public.events_intro')">
        <div class="mt-6 inline-flex rounded-lg border border-line bg-surface-sunken p-0.5 text-sm">
            <a href="{{ route('public.events.index') }}" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-surface text-fg shadow-xs' => ! $past, 'text-muted' => $past])>{{ __('public.upcoming') }}</a>
            <a href="{{ route('public.events.index', ['past' => 1]) }}" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-surface text-fg shadow-xs' => $past, 'text-muted' => ! $past])>{{ __('public.past') }}</a>
        </div>
    </x-public.band>
    <div class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <div class="space-y-3">
            @forelse ($events as $event) <x-public.event-card :event="$event" /> @empty <x-public.placeholder :title="__('public.no_events')">{{ __('public.no_events_help') }}</x-public.placeholder> @endforelse
        </div>
        <div class="mt-8">{{ $events->links() }}</div>
    </div>
</x-layouts::public>
