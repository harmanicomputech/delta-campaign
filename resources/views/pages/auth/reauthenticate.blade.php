<x-layouts::auth :title="__($mode === 'unlock' ? 'auth.unlock_title' : 'auth.confirm_title')">
    <div class="mb-8">
        <span class="mb-5 flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="lock-keyhole" class="size-5" /></span>
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ __($mode === 'unlock' ? 'auth.unlock_title' : 'auth.confirm_title') }}</h1>
        <p class="mt-2 text-[0.9375rem] text-muted">{{ __($mode === 'unlock' ? 'auth.unlock_intro' : 'auth.confirm_intro') }}</p>
    </div>

    @if (session('info')) <x-ui.alert tone="info" class="mb-6">{{ session('info') }}</x-ui.alert> @endif
    @if ($pinLocked) <x-ui.alert tone="warning" class="mb-6">{{ __('auth.pin_locked') }}</x-ui.alert> @endif

    @if ($hasPin && ! $codeSent)
        <form method="POST" action="{{ route('reauthenticate') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            <x-ui.input name="pin" type="password" :label="__('auth.pin')" inputmode="numeric" autocomplete="current-password" maxlength="6" autofocus required class="[&_input]:text-center [&_input]:text-lg [&_input]:tracking-[0.5em]" />
            <x-ui.button type="submit" size="lg" class="w-full">{{ __('auth.continue') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('reauthenticate.code') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-[0.8125rem] font-medium text-primary hover:text-primary-hover">{{ __('auth.use_code_instead') }}</button>
        </form>
    @elseif (! $codeSent)
        <form method="POST" action="{{ route('reauthenticate.code') }}">
            @csrf
            <x-ui.button type="submit" size="lg" class="w-full" icon="message-square">{{ __('auth.send_code_to', ['phone' => $masked]) }}</x-ui.button>
        </form>
    @else
        <form method="POST" action="{{ route('reauthenticate') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            <x-ui.otp-input />
            <x-ui.button type="submit" size="lg" class="w-full">{{ __('auth.continue') }}</x-ui.button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" x-data x-on:submit.prevent="$store.sync.forgetLists().finally(() => $el.submit())" class="mt-8 text-center">
        @csrf
        <button type="submit" class="text-[0.8125rem] font-medium text-muted hover:text-fg">{{ __('auth.sign_out_instead') }}</button>
    </form>
</x-layouts::auth>
