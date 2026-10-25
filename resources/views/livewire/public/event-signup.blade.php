<div class="rounded-2xl border border-line bg-surface p-5 shadow-sm">
    <h2 class="font-display text-lg font-semibold text-fg">{{ __('public.register_event') }}</h2>
    @if ($ticketUrl)
        <div class="mt-4 rounded-xl bg-success-soft p-4 text-sm" role="status">
            <p class="font-semibold text-fg">{{ __('public.event_registered') }}</p>
            <a href="{{ $ticketUrl }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-primary hover:underline">{{ __('public.view_ticket') }} <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    @elseif (! $event->acceptsRegistrations())
        <p class="mt-3 text-sm text-muted">{{ $event->status->value === 'cancelled' ? __('events.cancelled') : ($spaces === 0 ? __('events.full') : __('events.closed')) }}</p>
    @else
        @if ($spaces !== null) <p class="mt-1 text-sm text-muted">{{ trans_choice('public.spaces_left', $spaces, ['count' => $spaces]) }}</p> @endif
        <form wire:submit="register" class="mt-4 space-y-4" novalidate>
            <x-public.honeypot id="event" />
            @error('form') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
            <x-public.verify-phone :verified="$verifiedPhone" :masked="$masked" :code-sent="$codeSent" />
            @error('phone') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            <x-ui.input wire:model="fullName" :label="__('supporters.full_name')" autocomplete="name" required />
            <x-ui.button type="submit" class="w-full" loading="register" :disabled="! $verifiedPhone">{{ __('public.register') }}</x-ui.button>
            <p class="text-xs text-subtle">{{ __('public.event_privacy') }}</p>
        </form>
    @endif
</div>
