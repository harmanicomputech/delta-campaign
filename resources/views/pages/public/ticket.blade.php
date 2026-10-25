@php($start = $event->starts_at->timezone(config('campaign.display_timezone')))
<x-layouts::public :title="__('public.ticket')">
    <div class="mx-auto max-w-md px-5 py-12">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface shadow-lg">
            <div class="bg-nav px-6 py-5 text-white">
                <p class="text-xs font-semibold tracking-[0.14em] text-accent-300 uppercase">{{ __('public.ticket') }}</p>
                <h1 class="mt-1 font-display text-xl font-semibold">{{ $event->translated('title') }}</h1>
                <p class="mt-1 text-sm text-nav-fg/80">{{ $start->format('D j M Y, g:ia') }} · {{ $event->venue }}</p>
            </div>
            <div class="p-6 text-center">
                @if ($event->status->value === 'cancelled')
                    <x-ui.alert tone="danger">{{ __('events.cancelled') }}</x-ui.alert>
                @elseif ($registration->status->value === 'cancelled')
                    <x-ui.alert tone="warning">{{ __('events.ticket_cancelled') }}</x-ui.alert>
                @else
                    <div class="mx-auto size-56 rounded-lg bg-white p-2 [&_svg]:size-full" x-data="qrCode(@js(route('workspace.events.check-in', $registration->ticket_token)))" role="img" aria-label="{{ __('public.ticket_qr') }}"></div>
                    <p class="mt-3 font-mono text-sm tracking-widest text-fg">{{ strtoupper(substr($registration->ticket_token, 0, 8)) }}</p>
                    @if ($registration->status->value === 'attended') <x-ui.badge tone="success" class="mt-3" icon="check">{{ __('public.checked_in') }}</x-ui.badge> @endif
                    <p class="mt-4 text-sm text-muted">{{ __('public.ticket_help', ['name' => strtok($registration->full_name, ' ')]) }}</p>
                @endif
            </div>
        </div>
        <p class="mt-4 text-center text-xs text-subtle">{{ __('public.ticket_privacy') }}</p>
    </div>
</x-layouts::public>
