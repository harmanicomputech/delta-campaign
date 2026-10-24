@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'type' => 'text',
    'hint' => null,
    'icon' => null,
    'prefix' => null,
    'required' => false,
    'optional' => false,
    'error' => null,
])
@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str_replace(['.', '[', ']'], '-', $name) : 'f-'.\Illuminate\Support\Str::random(6);
    $message = $error ?? ($name ? $errors->first($name) : null);
    $describedBy = $message ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp
<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :required="$required" :optional="$optional" :class="$attributes->get('class')">
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
        @elseif ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center border-r border-line pr-2.5 pl-3 text-sm text-muted">{{ $prefix }}</span>
        @endif
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
            @if ($required) required @endif
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->class(['form-control', 'pl-9' => $icon, 'pl-[4.25rem]' => $prefix && ! $icon]) }}
        >
    </div>
</x-ui.field>
