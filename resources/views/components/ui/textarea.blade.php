@props(['name' => null, 'id' => null, 'label' => null, 'hint' => null, 'required' => false, 'optional' => false, 'rows' => 4, 'error' => null])
@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str_replace(['.', '[', ']'], '-', $name) : 'f-'.\Illuminate\Support\Str::random(6);
    $message = $error ?? ($name ? $errors->first($name) : null);
@endphp
<x-ui.field :label="$label" :for="$id" :hint="$hint" :error="$message" :required="$required" :optional="$optional" :class="$attributes->get('class')">
    <textarea id="{{ $id }}" rows="{{ $rows }}"
        @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->class('form-control min-h-24 resize-y py-2.5') }}>{{ $slot }}</textarea>
</x-ui.field>
