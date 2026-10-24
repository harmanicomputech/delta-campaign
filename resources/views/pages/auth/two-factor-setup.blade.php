<x-layouts::auth :title="__('auth.setup_title')">
    @if ($recoveryCodes)
        <div class="mb-6">
            <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-success-soft text-success"><x-icon name="key-round" class="size-5" /></span>
            <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.recovery_title') }}</h1>
            <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.recovery_intro') }}</p>
        </div>
        <ul class="grid grid-cols-2 gap-2 rounded-xl border border-line bg-surface-sunken p-4 font-mono text-sm" aria-label="Recovery codes">
            @foreach ($recoveryCodes as $code)
                <li class="rounded-md bg-surface px-3 py-2 text-center tracking-wider select-all">{{ $code }}</li>
            @endforeach
        </ul>
        <x-ui.button size="lg" class="mt-6 w-full" :href="route('workspace.dashboard')">{{ __('auth.recovery_done') }}</x-ui.button>
    @else
        <div class="mb-6">
            <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="shield-check" class="size-5" /></span>
            <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __('auth.setup_title') }}</h1>
            <p class="mt-2 text-[0.9375rem] text-muted">{{ __('auth.setup_intro') }}</p>
        </div>

        <div class="mb-6 flex flex-col items-center gap-4 rounded-xl border border-line bg-surface-sunken/50 p-5 sm:flex-row sm:items-start">
            <div x-data="qrCode(@js($uri))" class="size-40 shrink-0 rounded-lg bg-white p-2 [&_svg]:size-full" role="img" aria-label="QR code for your authenticator app"></div>
            <div class="min-w-0 text-[0.8125rem] text-muted">
                <p>{{ __('auth.setup_manual') }}</p>
                <p class="mt-2 font-mono text-sm break-all text-fg select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('two-factor.setup.store') }}" class="space-y-6">
            @csrf
            <x-ui.otp-input />
            <x-ui.button type="submit" size="lg" class="w-full">{{ __('auth.setup_confirm') }}</x-ui.button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-[0.8125rem] font-medium text-muted hover:text-fg">{{ __('auth.sign_out_instead') }}</button>
    </form>
</x-layouts::auth>
