<x-layouts::auth :title="__('auth.verify_title')">
    <x-ui.alert tone="warning" class="mb-8">{{ __('auth.preview_note') }}</x-ui.alert>

    <a href="{{ route('design-system.sign-in') }}" class="mb-6 inline-flex items-center gap-1.5 text-[0.8125rem] font-medium text-muted hover:text-fg">
        <x-icon name="arrow-left" class="size-4" /> {{ __('auth.change_number') }}
    </a>

    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="message-square" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.verify_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.verify_intro', ['phone' => \App\Support\Phone\NigerianPhoneNumber::mask('+2348030000000')]) }}</p>
    </div>

    <div class="space-y-6">
        <x-ui.otp-input />
        <x-ui.button size="lg" class="w-full" :href="route('design-system.shell')">{{ __('auth.verify') }}</x-ui.button>
        <div class="flex items-center justify-between text-[0.8125rem]"
             x-data="{ seconds: 45, timer: null, start() { this.seconds = 45; clearInterval(this.timer); this.timer = setInterval(() => this.seconds > 0 ? this.seconds-- : clearInterval(this.timer), 1000) } }"
             x-init="start()">
            <span class="text-subtle" x-show="seconds > 0" x-text="@js(__('auth.resend_in', ['seconds' => '__S__'])).replace('__S__', seconds)"></span>
            <button type="button" class="font-medium text-primary hover:text-primary-hover" x-show="seconds === 0" x-cloak
                    x-on:click="start(); $store.toasts.push({ type: 'info', title: @js(__('auth.preview_note')) })">{{ __('auth.resend') }}</button>
        </div>
    </div>
</x-layouts::auth>
