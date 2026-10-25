<div class="space-y-8">
    <section class="space-y-3">
        <h2 class="font-semibold text-fg">{{ __('public.prefs_verify') }}</h2>
        <p class="text-sm text-muted">{{ __('public.prefs_verify_help') }}</p>
        <x-public.honeypot id="prefs" />
        <x-public.verify-phone :verified="$verifiedPhone" :masked="$masked" :code-sent="$codeSent" />
        @error('phone') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
        @error('form') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
    </section>

    @if ($summary !== null)
        <section class="rounded-2xl border border-line bg-surface p-6">
            <h2 class="font-display text-lg font-semibold text-fg">{{ __('public.prefs_messages') }}</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach (['sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $label)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <span><span class="block font-medium text-fg">{{ $label }}</span><span class="text-sm text-muted">{{ $summary[$channel] ? __('public.prefs_on') : __('public.prefs_off') }}</span></span>
                        @if ($summary[$channel])
                            <x-ui.button size="sm" variant="secondary" wire:click="setConsent('{{ $channel }}', false)">{{ __('public.turn_off') }}</x-ui.button>
                        @else
                            <x-ui.button size="sm" wire:click="setConsent('{{ $channel }}', true)">{{ __('public.turn_on') }}</x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <x-ui.button variant="ghost" class="mt-3" icon="ban" wire:click="stopAll" wire:confirm="{{ __('public.stop_all_confirm') }}">{{ __('public.stop_all') }}</x-ui.button>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-6">
            <h2 class="font-display text-lg font-semibold text-fg">{{ __('public.prefs_rights') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('public.prefs_rights_help', ['days' => (int) config('campaign.privacy.dsr_response_days')]) }}</p>
            <form wire:submit="submitRequest" class="mt-4 space-y-4">
                <x-ui.select wire:model.live="requestType" :label="__('public.request_type')" :options="$types" />
                <x-ui.textarea wire:model="details" :label="__('public.request_details')" :hint="__('public.request_details_hint')" rows="3" :optional="$requestType !== 'rectification'" />
                <x-ui.button type="submit" loading="submitRequest">{{ __('public.submit_request') }}</x-ui.button>
            </form>
            @if ($requests->isNotEmpty())
                <ul class="mt-6 divide-y divide-line border-t border-line text-sm">
                    @foreach ($requests as $request)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span><span class="font-mono text-xs">{{ $request->reference }}</span> · {{ $request->type->label() }}</span>
                            <x-ui.badge size="sm" :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
