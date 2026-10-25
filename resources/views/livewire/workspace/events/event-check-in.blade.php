@php($tz = config('campaign.display_timezone'))
<div class="mx-auto max-w-lg">
    <x-ui.page-header :title="__('events.check_in')" :description="__('events.check_in_help')" />
    <x-ui.card>
        @if ($registration === null)
            <form wire:submit="lookup" class="space-y-4">
                <x-ui.input name="token" wire:model="token" :label="__('events.ticket_code')" :hint="__('events.ticket_code_hint')" autocomplete="off" autofocus required />
                <x-ui.button type="submit" icon="search" class="w-full" loading="lookup">{{ __('events.find_ticket') }}</x-ui.button>
            </form>
        @else
            <div class="text-center">
                @if ($result === 'checked_in')
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-7" /></span>
                    <p class="mt-3 font-display text-lg font-semibold text-fg" role="status">{{ __('events.checked_in_ok') }}</p>
                @elseif ($result === 'already' || $registration->status->value === 'attended')
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-warning-soft text-warning"><x-icon name="info" class="size-7" /></span>
                    <p class="mt-3 font-display text-lg font-semibold text-fg" role="status">{{ __('events.already_checked_in', ['time' => $registration->checked_in_at?->timezone($tz)->format('H:i')]) }}</p>
                @endif
                <p class="mt-4 text-xl font-semibold text-fg">{{ $registration->full_name }}</p>
                <p class="font-mono text-sm text-subtle">{{ $registration->maskedPhone() }}</p>
                <p class="mt-2 text-sm text-muted">{{ $registration->event->title_en }} · {{ $registration->event->starts_at->timezone($tz)->format('j M, g:ia') }}</p>
                <x-ui.badge :tone="$registration->status->tone()" class="mt-3">{{ $registration->status->label() }}</x-ui.badge>
                @error('token') <x-ui.alert tone="danger" class="mt-4 text-left">{{ $message }}</x-ui.alert> @enderror
            </div>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                @if ($result === null && $registration->status->value === 'registered')
                    <x-ui.button icon="check" class="flex-1" wire:click="checkIn" loading="checkIn">{{ __('events.confirm_check_in') }}</x-ui.button>
                @endif
                <x-ui.button variant="secondary" icon="scan-line" class="flex-1" wire:click="next">{{ __('events.next_ticket') }}</x-ui.button>
            </div>
        @endif
    </x-ui.card>
    <p class="mt-4 text-center text-xs text-subtle">{{ __('events.scan_tip') }}</p>
</div>
