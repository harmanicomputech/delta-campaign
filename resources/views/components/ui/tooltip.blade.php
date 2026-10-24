{{-- Hover/focus hint for icon-only or complex controls. The trigger stays the accessible element. --}}
@props(['text', 'position' => 'top'])
@php($positions = ['top' => 'bottom-full left-1/2 mb-2 -translate-x-1/2', 'bottom' => 'top-full left-1/2 mt-2 -translate-x-1/2', 'right' => 'left-full top-1/2 ml-2 -translate-y-1/2'])
<span {{ $attributes->class('group/tip relative inline-flex') }}>
    {{ $slot }}
    <span role="tooltip" class="pointer-events-none absolute z-40 w-max max-w-56 rounded-md bg-ink-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-md transition-opacity duration-150 group-focus-within/tip:opacity-100 group-hover/tip:opacity-100 group-hover/tip:delay-300 dark:bg-ink-200 dark:text-ink-950 {{ $positions[$position] ?? $positions['top'] }}">{{ $text }}</span>
</span>
