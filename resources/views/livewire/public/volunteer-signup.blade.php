<div>
    @if ($done)
        <div class="rounded-2xl border border-success/30 bg-success-soft p-8 text-center" role="status">
            <x-icon name="hand-heart" class="mx-auto size-10 text-success" />
            <h2 class="mt-3 font-display text-2xl font-semibold text-fg">{{ $existing ? __('public.volunteer_existing') : __('public.volunteer_done_title') }}</h2>
            <p class="mt-2 text-muted">{{ __('public.volunteer_done_body') }}</p>
        </div>
    @else
        <form wire:submit="apply" class="space-y-6" novalidate>
            <x-public.honeypot id="volunteer" />
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
                <div>
                    <p class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.skills') }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">@foreach ($skillOptions as $v => $l) <x-ui.checkbox wire:model="skills" value="{{ $v }}" :label="$l" /> @endforeach</div>
                </div>
                <div>
                    <p class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.availability') }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">@foreach ($availabilityOptions as $v => $l) <x-ui.checkbox wire:model="availability" value="{{ $v }}" :label="$l" /> @endforeach</div>
                </div>
                <div>
                    <x-ui.checkbox wire:model="adult" :label="__('public.adult')" />
                    @error('adult') <p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                </div>
                @if ($notice) <p class="text-xs text-muted">{{ __('public.volunteer_privacy') }} <a href="{{ route('public.privacy') }}" class="text-primary underline">{{ __('public.read_notice') }}</a></p> @endif
            </fieldset>
            <x-ui.button type="submit" size="lg" loading="apply" :disabled="! $verifiedPhone">{{ __('public.apply') }}</x-ui.button>
        </form>
    @endif
</div>
