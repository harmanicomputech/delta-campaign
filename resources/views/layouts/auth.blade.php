{{-- Authentication layout: focused form panel with a quiet brand panel on large screens. --}}
@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <x-shell.head :title="$title" />
</head>
<body x-data class="min-h-dvh bg-surface">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
        <div class="flex flex-col px-5 py-6 sm:px-10 lg:px-16">
            <div class="flex items-center justify-between">
                <x-shell.brand />
                <div class="flex items-center gap-1">
                    <x-shell.locale-switcher />
                    <x-shell.theme-switcher />
                </div>
            </div>
            <main id="main" class="flex flex-1 items-center py-10">
                <div class="mx-auto w-full max-w-[400px]">{{ $slot }}</div>
            </main>
            <p class="flex items-center justify-center gap-2 text-xs text-subtle lg:justify-start">
                <x-icon name="lock-keyhole" class="size-3.5" /> {{ __('auth.secure_note') }}
            </p>
        </div>

        <aside class="relative hidden overflow-hidden bg-nav lg:block" aria-hidden="true">
            {{-- Abstract, brand-neutral composition. Replace with approved imagery when supplied. --}}
            <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_100%_0%,rgb(35_145_95/0.55),transparent_60%),radial-gradient(90%_70%_at_0%_100%,rgb(226_165_47/0.22),transparent_55%)]"></div>
            <svg class="absolute inset-0 size-full opacity-[0.14]" xmlns="http://www.w3.org/2000/svg">
                <defs><pattern id="auth-grid" width="44" height="44" patternUnits="userSpaceOnUse"><path d="M44 0H0v44" fill="none" stroke="white" stroke-width="0.6" /></pattern></defs>
                <rect width="100%" height="100%" fill="url(#auth-grid)" />
            </svg>
            <div class="absolute inset-x-16 bottom-16">
                <div class="mb-8 flex gap-2">
                    @foreach (['bg-brand-300', 'bg-brand-500', 'bg-accent-400'] as $bar)
                        <span class="h-1 w-10 rounded-full {{ $bar }}"></span>
                    @endforeach
                </div>
                <p class="font-display text-[2.5rem] leading-[1.1] font-medium tracking-tight text-white">{{ config('app.name') }}</p>
                <p class="mt-4 max-w-md text-[0.9375rem] leading-7 text-nav-fg/80">{{ __('shell.brand_placeholder') }}</p>
            </div>
        </aside>
    </div>
    <x-shell.toasts />
    @livewireScriptConfig
</body>
</html>
