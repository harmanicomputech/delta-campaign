<x-layouts::auth :title="__('auth.verify_title')">
    <a href="{{ route('login') }}" class="mb-6 inline-flex items-center gap-1.5 text-[0.8125rem] font-medium text-muted hover:text-fg">
        <x-icon name="arrow-left" class="size-4" /> {{ __('auth.change_number') }}
    </a>

    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="message-square" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.verify_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.verify_intro', ['phone' => $masked]) }}</p>
    </div>

    <form method="POST" action="{{ route('login.verify.store') }}" class="space-y-6" x-data x-on:submit="$el.querySelector('[type=submit]').disabled = true">
        @csrf
        <x-ui.otp-input />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('auth.verify') }}</x-ui.button>
    </form>

    <form method="POST" action="{{ route('login.resend') }}" class="mt-6 flex items-center justify-between text-[0.8125rem]"
          x-data="{ seconds: {{ (int) $resendIn }}, init() { const t = setInterval(() => this.seconds > 0 ? this.seconds-- : clearInterval(t), 1000) } }">
        @csrf
        <span class="text-subtle" x-show="seconds > 0" x-text="@js(__('auth.resend_in', ['seconds' => '__S__'])).replace('__S__', seconds)"></span>
        <button type="submit" class="font-medium text-primary hover:text-primary-hover disabled:opacity-50" x-bind:disabled="seconds > 0" x-show="seconds === 0" x-cloak>{{ __('auth.resend') }}</button>
        <noscript><button type="submit" class="font-medium text-primary">{{ __('auth.resend') }}</button></noscript>
    </form>
</x-layouts::auth>
