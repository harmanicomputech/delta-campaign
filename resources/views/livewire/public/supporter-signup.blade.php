<div>
    @if ($reference)
        <div class="rounded-2xl border border-success/30 bg-success-soft p-8 text-center" role="status">
            <x-icon name="circle-check" class="mx-auto size-10 text-success" />
            <h2 class="mt-3 font-display text-2xl font-semibold text-fg">{{ $existing ? __('public.already_registered') : __('public.registered_title') }}</h2>
            <p class="mt-2 text-muted">{{ __('public.registered_body', ['ref' => $reference]) }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('public.volunteer') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary">{{ __('public.cta_volunteer') }}</a>
                <a href="{{ route('public.polling-unit') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-fg">{{ __('public.nav_polling_unit') }}</a>
            </div>
        </div>
    @else
        <form wire:submit="register" class="space-y-6" novalidate>
            <x-public.honeypot id="signup" />
            @error('form') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
            <fieldset class="space-y-3">
                <legend class="font-semibold text-fg">1. {{ __('public.step_phone') }}</legend>
                <x-public.verify-phone :verified="$verifiedPhone" :masked="$masked" :code-sent="$codeSent" />
                @error('phone') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </fieldset>
            <fieldset class="space-y-4" @disabled(! $verifiedPhone)>
                <legend class="font-semibold text-fg">2. {{ __('public.step_details') }}</legend>
                <x-ui.input wire:model="fullName" :label="__('supporters.full_name')" autocomplete="name" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="__('users.choose')" :options="$lgas->all()" required />
                    <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="$lgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$wards->all()" :disabled="$wards->isEmpty()" optional />
                </div>
            </fieldset>
            <fieldset class="space-y-3 rounded-xl border border-line p-5" @disabled(! $verifiedPhone)>
                <legend class="px-1 font-semibold text-fg">3. {{ __('public.step_consent') }}</legend>
                @if ($notice)
                    <p class="text-sm text-muted">{{ $notice->summary(app()->getLocale()) }} <a href="{{ route('public.privacy') }}" class="font-medium text-primary underline" target="_blank">{{ __('public.read_notice') }}</a></p>
                    <x-ui.checkbox wire:model="consentSms" :label="__('public.consent_sms')" />
                    <x-ui.checkbox wire:model="consentWhatsapp" :label="__('public.consent_whatsapp')" />
                    <p class="text-xs text-subtle">{{ __('public.consent_optional') }}</p>
                @else
                    <x-ui.alert tone="warning">{{ __('public.not_open') }}</x-ui.alert>
                @endif
                <x-ui.checkbox wire:model="volunteer" :label="__('supporters.volunteer')" />
                <div>
                    <x-ui.checkbox wire:model="adult" :label="__('public.adult')" />
                    @error('adult') <p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                </div>
            </fieldset>
            <x-ui.button type="submit" size="lg" class="w-full sm:w-auto" loading="register" :disabled="! $verifiedPhone">{{ __('public.register') }}</x-ui.button>
            <p class="text-xs text-subtle">{{ __('public.no_sensitive') }}</p>
        </form>
    @endif
</div>
