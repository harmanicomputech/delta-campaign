<x-layouts::auth :title="__('auth.sign_in')">
    <x-ui.alert tone="warning" class="mb-8">{{ __('auth.preview_note') }}</x-ui.alert>

    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="smartphone" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.sign_in_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.sign_in_intro') }}</p>
    </div>

    {{-- Preview: the fields are not submitted; "Send code" moves to the next preview screen. --}}
    <div class="space-y-6">
        <x-ui.phone-input name="phone" autofocus />
        <x-ui.button size="lg" class="w-full" icon-trailing="arrow-right" :href="route('design-system.verify')">{{ __('auth.send_code') }}</x-ui.button>
    </div>

    <p class="mt-8 text-[0.8125rem] leading-6 text-subtle">{{ __('auth.trouble') }}</p>
</x-layouts::auth>
