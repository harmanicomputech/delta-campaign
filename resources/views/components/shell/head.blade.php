@props(['title' => null, 'description' => null, 'robots' => 'noindex, nofollow'])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="{{ $robots }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#052418">
@if ($description) <meta name="description" content="{{ $description }}"> @endif
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
{{-- Apply the saved theme and sidebar state before first paint to avoid a flash. --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">(function(){try{var p=localStorage.getItem('theme')||'system';var d=p==='dark'||(p==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);var r=document.documentElement;r.dataset.theme=d?'dark':'light';r.dataset.sidebar=localStorage.getItem('sidebar-collapsed')==='true'?'collapsed':'expanded'}catch(e){document.documentElement.dataset.theme='light'}})();</script>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">window.__i18n = @json(['saved_offline' => __('sync.saved_offline')]);</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
{{ $slot }}
