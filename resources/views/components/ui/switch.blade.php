{{-- Accessible toggle switch. Bind with x-model / wire:model on the hidden checkbox. --}}
@props(['id' => null, 'label', 'description' => null])
@php($id ??= 'sw-'.\Illuminate\Support\Str::random(6))
<label for="{{ $id }}" class="flex cursor-pointer items-start justify-between gap-4">
    <span class="min-w-0 text-sm">
        <span class="font-medium text-fg">{{ $label }}</span>
        @if ($description) <span class="mt-0.5 block text-[0.8125rem] text-muted">{{ $description }}</span> @endif
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input id="{{ $id }}" type="checkbox" role="switch" {{ $attributes->class('peer sr-only') }}>
        <span class="h-6 w-10 rounded-full bg-line-strong transition-colors duration-200 peer-checked:bg-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring" aria-hidden="true"></span>
        <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-out peer-checked:translate-x-4" aria-hidden="true"></span>
    </span>
</label>
