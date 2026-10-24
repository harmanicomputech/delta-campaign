<x-layouts::auth :title="__('auth.two_factor_title')">
    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="shield-check" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.two_factor_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.two_factor_intro') }}</p>
    </div>

    <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="space-y-6">
        @csrf
        <x-ui.input name="code" :label="__('auth.two_factor_code')" inputmode="text" autocomplete="one-time-code" autofocus required maxlength="20" class="[&_input]:text-center [&_input]:text-lg [&_input]:tracking-widest" />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('auth.verify') }}</x-ui.button>
    </form>
</x-layouts::auth>
