{{--
    Neutral monogram + application name. No campaign logo is shown until approved assets
    are supplied (docs/PROJECT_AUDIT.md §4).
--}}
@props(['inverse' => false, 'collapsible' => false, 'compact' => false, 'subtitle' => true])
@php($initials = collect(explode(' ', (string) config('app.name')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''))
<span {{ $attributes->class('flex min-w-0 items-center gap-3') }}>
    <span class="relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-[0.625rem] bg-gradient-to-br from-brand-400 via-brand-600 to-brand-800 text-[0.8125rem] font-bold tracking-tight text-white shadow-sm ring-1 ring-white/15" aria-hidden="true">
        <span class="absolute -top-3 -right-3 size-6 rounded-full bg-accent-400/70 blur-[6px]"></span>
        <span class="relative">{{ $initials }}</span>
    </span>
    <span @class(['min-w-0 leading-tight', 'sidebar-collapsed:hidden' => $collapsible, 'hidden sm:block' => $compact])>
        <span @class(['block truncate text-sm font-semibold tracking-tight', 'text-white' => $inverse, 'text-fg' => ! $inverse])>{{ config('app.name') }}</span>
        @if ($subtitle)
            <span @class(['block truncate text-[0.6875rem]', 'text-nav-muted' => $inverse, 'text-subtle' => ! $inverse])>{{ __('shell.workspace') }}</span>
        @endif
    </span>
</span>
