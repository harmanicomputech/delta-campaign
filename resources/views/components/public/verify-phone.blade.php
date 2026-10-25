{{-- Phone verification step used by public forms (Livewire component with the VerifiesPhone trait). --}}
@props(['verified' => null, 'masked' => '', 'codeSent' => false])
<div class="rounded-xl border border-line bg-surface-sunken/50 p-4">
    @if ($verified)
        <div class="flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-sm text-fg"><x-icon name="circle-check" class="size-5 text-success" /> {{ __('public.verified_as', ['phone' => $masked]) }}</p>
            <button type="button" wire:click="changeNumber" class="text-[0.8125rem] font-medium text-primary hover:underline">{{ __('public.change_number') }}</button>
        </div>
    @elseif (! $codeSent)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><x-ui.phone-input wire:model="phone" name="phone" :hint="false" /></div>
            <x-ui.button type="button" variant="secondary" wire:click="sendCode" loading="sendCode">{{ __('public.send_code') }}</x-ui.button>
        </div>
        <p class="mt-1.5 text-[0.8125rem] text-muted">{{ __('public.phone_hint') }}</p>
    @else
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><x-ui.input wire:model="code" :label="__('public.code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" /></div>
            <x-ui.button type="button" wire:click="confirmCode" loading="confirmCode">{{ __('public.confirm_code') }}</x-ui.button>
        </div>
        <p class="mt-1.5 text-[0.8125rem] text-muted">{{ __('public.code_hint') }}</p>
        <button type="button" wire:click="changeNumber" class="mt-2 text-[0.8125rem] text-muted hover:text-primary">{{ __('public.use_other_number') }}</button>
    @endif
</div>
