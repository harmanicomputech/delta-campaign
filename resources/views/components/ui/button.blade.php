{{--
    Button / link button.
    variant: primary | secondary | soft | ghost | danger   size: sm | md | lg
    href turns it into a link. "loading" names a Livewire action to show a spinner for.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconTrailing' => null,
    'href' => null,
    'type' => 'button',
    'square' => false,
    'loading' => null,
])

@php
    $base = 'relative inline-flex select-none items-center justify-center gap-2 whitespace-nowrap font-medium transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-out active:translate-y-px disabled:pointer-events-none disabled:opacity-55 aria-disabled:pointer-events-none aria-disabled:opacity-55';

    $variants = [
        'primary' => 'bg-primary text-on-primary shadow-sm hover:bg-primary-hover',
        'secondary' => 'border border-line-strong bg-surface text-fg shadow-xs hover:bg-surface-hover hover:border-ink-400 dark:hover:border-white/25',
        'soft' => 'bg-primary-soft text-primary-soft-fg hover:bg-primary-soft/70 dark:hover:bg-primary-soft/80',
        'ghost' => 'text-muted hover:bg-surface-sunken hover:text-fg',
        'danger' => 'bg-danger text-on-danger shadow-sm hover:bg-danger-hover',
    ];

    $sizes = $square
        ? ['sm' => 'size-8 rounded-md', 'md' => 'size-10 rounded-md', 'lg' => 'size-12 rounded-lg']
        : ['sm' => 'h-8 rounded-md px-3 text-[0.8125rem]', 'md' => 'h-10 rounded-md px-4 text-sm', 'lg' => 'h-12 rounded-lg px-5 text-[0.9375rem]'];

    $iconSize = $size === 'lg' ? 'size-5' : 'size-4';
    $classes = implode(' ', [$base, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" :class="$iconSize" /> @endif
        {{ $slot }}
        @if ($iconTrailing) <x-icon :name="$iconTrailing" :class="$iconSize" /> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif>
        @if ($loading)
            <span wire:loading.flex wire:target="{{ $loading }}" class="absolute inset-0 hidden items-center justify-center">
                <x-icon name="loader-circle" :class="$iconSize.' animate-spin'" />
            </span>
            <span wire:loading.class="invisible" wire:target="{{ $loading }}" class="inline-flex items-center gap-2">
        @endif
        @if ($icon) <x-icon :name="$icon" :class="$iconSize" /> @endif
        {{ $slot }}
        @if ($iconTrailing) <x-icon :name="$iconTrailing" :class="$iconSize" /> @endif
        @if ($loading) </span> @endif
    </button>
@endif
