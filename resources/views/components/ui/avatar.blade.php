@props(['name' => '', 'size' => 'md'])
@php
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $sizes = ['sm' => 'size-7 text-[0.6875rem]', 'md' => 'size-9 text-xs', 'lg' => 'size-12 text-sm'];
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-800 font-semibold text-white ring-2 ring-surface', $sizes[$size] ?? $sizes['md']]) }} aria-hidden="true">{{ $initials ?: '•' }}</span>
