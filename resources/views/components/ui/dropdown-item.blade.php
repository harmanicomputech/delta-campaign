@props(['href' => null, 'icon' => null, 'danger' => false, 'active' => false])
@php($classes = 'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm outline-none transition-colors focus:bg-surface-sunken hover:bg-surface-sunken '.($danger ? 'text-danger' : 'text-fg'))
@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4 {{ $danger ? '' : 'text-subtle' }}" /> @endif
        <span class="flex-1">{{ $slot }}</span>
        @if ($active) <x-icon name="check" class="size-4 text-primary" /> @endif
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" role="menuitem" {{ $attributes->except('type')->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4 {{ $danger ? '' : 'text-subtle' }}" /> @endif
        <span class="flex-1">{{ $slot }}</span>
        @if ($active) <x-icon name="check" class="size-4 text-primary" /> @endif
    </button>
@endif
