{{-- tone: neutral | brand | success | warning | danger | info | accent --}}
@props(['tone' => 'neutral', 'dot' => false, 'icon' => null, 'size' => 'md'])

@php
    $tones = [
        'neutral' => 'bg-surface-sunken text-muted ring-line',
        'brand' => 'bg-primary-soft text-primary-soft-fg ring-primary/20',
        'success' => 'bg-success-soft text-success ring-success/20',
        'warning' => 'bg-warning-soft text-warning ring-warning/25',
        'danger' => 'bg-danger-soft text-danger ring-danger/20',
        'info' => 'bg-info-soft text-info ring-info/20',
        'accent' => 'bg-accent-100 text-accent-800 ring-accent-300/50 dark:bg-accent-400/12 dark:text-accent-300 dark:ring-accent-400/25',
    ];
    $sizes = ['sm' => 'h-5 px-1.5 text-[0.6875rem] gap-1', 'md' => 'h-6 px-2 text-xs gap-1.5'];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full font-medium ring-1 ring-inset whitespace-nowrap', $tones[$tone] ?? $tones['neutral'], $sizes[$size] ?? $sizes['md']]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    @if ($icon)
        <x-icon :name="$icon" class="size-3.5" />
    @endif
    {{ $slot }}
</span>
