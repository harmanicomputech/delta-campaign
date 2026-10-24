{{-- Native select (fast, accessible, mobile-friendly). options: [value => label]. --}}
@props(['name' => null, 'id' => null, 'label' => null, 'hint' => null, 'options' => [], 'placeholder' => null, 'required' => false, 'optional' => false, 'error' => null])
@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str_replace(['.', '[', ']'], '-', $name) : 'f-'.\Illuminate\Support\Str::random(6);
    $message = $error ?? ($name ? $errors->first($name) : null);
@endphp
<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :required="$required" :optional="$optional" :class="$attributes->get('class')">
    <div class="relative">
        <select id="{{ $id }}"
            @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
            @if ($required) required @endif
            @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except('class')->class('form-control appearance-none pr-9') }}>
            @if ($placeholder) <option value="">{{ $placeholder }}</option> @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-icon name="chevrons-up-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-subtle" />
    </div>
</x-ui.field>
