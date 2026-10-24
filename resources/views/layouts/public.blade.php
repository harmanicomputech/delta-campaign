{{-- Public website layout (expanded into the full site in Phase 10). --}}
@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <x-shell.head :title="$title" :description="$description" robots="index, follow" />
</head>
<body x-data class="flex min-h-dvh flex-col bg-canvas">
    <a href="#main" class="sr-only z-[80] rounded-md bg-primary px-4 py-2 text-on-primary focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('ui.skip_to_content') }}</a>
    <header class="absolute inset-x-0 top-0 z-20">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-5 sm:px-8">
            <a href="{{ route('home') }}" class="rounded-lg"><x-shell.brand inverse :subtitle="false" /></a>
            <x-shell.locale-switcher inverse />
        </div>
    </header>
    <main id="main" class="flex-1">{{ $slot }}</main>
    <footer class="border-t border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-8 text-[0.8125rem] text-subtle sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <p>© {{ now()->year }} {{ config('app.name') }}</p>
            <p>{{ __('public.footer_note') }}</p>
        </div>
    </footer>
    @livewireScriptConfig
</body>
</html>
