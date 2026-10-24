<x-layouts::auth :title="__('auth.sign_in')">
    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="smartphone" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.sign_in_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.sign_in_intro') }}</p>
    </div>

    @if (session('warning')) <x-ui.alert tone="warning" class="mb-6">{{ session('warning') }}</x-ui.alert> @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-6" novalidate>
        @csrf
        <x-ui.phone-input name="phone" :value="old('phone')" autofocus />
        <x-ui.button type="submit" size="lg" class="w-full" icon-trailing="arrow-right">{{ __('auth.send_code') }}</x-ui.button>
    </form>

    <p class="mt-8 text-[0.8125rem] leading-6 text-subtle">{{ __('auth.trouble') }}</p>
</x-layouts::auth>
