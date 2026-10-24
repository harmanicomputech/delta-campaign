{{--
    Shared error page. Self-contained: no Livewire, sessions or database, so it renders
    even when those are the cause of the error.
--}}
@php
    $status = (int) ($status ?? 500);
    $requestId = \Illuminate\Support\Facades\Context::get('request_id');
    $icons = [403 => 'lock-keyhole', 404 => 'map', 419 => 'clock', 429 => 'gauge', 500 => 'triangle-alert', 503 => 'settings'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __("errors.{$status}.title") }} · {{ config('app.name') }}</title>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">(function(){try{var p=localStorage.getItem('theme')||'system';document.documentElement.dataset.theme=(p==='dark'||(p==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light'}catch(e){}})();</script>
    @vite('resources/css/app.css')
</head>
<body class="grid min-h-dvh place-items-center bg-canvas px-5">
    <main class="w-full max-w-md py-16 text-center">
        <div class="mx-auto mb-8 flex size-14 items-center justify-center rounded-2xl border border-line bg-surface text-primary shadow-sm">
            <x-icon :name="$icons[$status] ?? 'circle-alert'" class="size-6" />
        </div>
        <p class="text-sm font-semibold text-primary tabular">{{ $status }}</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-fg sm:text-3xl">{{ __("errors.{$status}.title") }}</h1>
        <p class="mt-3 text-[0.9375rem] leading-7 text-muted">{{ __("errors.{$status}.body") }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <x-ui.button :href="url('/')" icon="house">{{ __('errors.home') }}</x-ui.button>
        </div>
        @if ($requestId)
            <p class="mt-10 text-xs text-subtle">{{ __('errors.reference') }}: <span class="font-mono select-all">{{ $requestId }}</span></p>
        @endif
    </main>
</body>
</html>
