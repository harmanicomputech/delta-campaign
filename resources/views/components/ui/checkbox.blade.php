@props(['name' => null, 'id' => null, 'label' => null, 'description' => null])
@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= 'f-'.($name ? str_replace(['.', '[', ']'], '-', $name) : \Illuminate\Support\Str::random(6)).($attributes->get('value') ? '-'.$attributes->get('value') : '');
@endphp
<label for="{{ $id }}" class="group flex cursor-pointer items-start gap-3">
    <input id="{{ $id }}" type="checkbox"
        @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
        {{ $attributes->class('mt-0.5 size-[1.125rem] shrink-0 cursor-pointer rounded border-line-strong bg-surface accent-[var(--c-primary)]') }}>
    @if ($label || $description)
        <span class="min-w-0 text-sm">
            @if ($label) <span class="font-medium text-fg">{{ $label }}</span> @endif
            @if ($description) <span class="mt-0.5 block text-[0.8125rem] text-muted">{{ $description }}</span> @endif
        </span>
    @endif
</label>
