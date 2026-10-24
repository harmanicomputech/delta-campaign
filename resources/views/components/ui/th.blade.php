{{-- Column header. With "sortable", calls the Livewire sortBy($column) action and shows the direction. --}}
@props(['column' => null, 'sortable' => false, 'sort' => null, 'direction' => 'asc', 'align' => 'left'])
@php
    $active = $sortable && $sort === $column;
    $ariaSort = $active ? ($direction === 'asc' ? 'ascending' : 'descending') : ($sortable ? 'none' : null);
@endphp
<th scope="col" @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif
    {{ $attributes->class(['border-b border-line px-4 py-2.5 text-xs font-semibold tracking-wide whitespace-nowrap text-muted uppercase first:pl-5 last:pr-5', 'text-right' => $align === 'right']) }}>
    @if ($sortable)
        <button type="button" wire:click="sortBy('{{ $column }}')" class="group inline-flex items-center gap-1 uppercase hover:text-fg" aria-label="{{ __('ui.sort_by', ['column' => trim(strip_tags($slot))]) }}">
            {{ $slot }}
            <x-icon :name="$active ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'" @class(['size-3.5', 'text-primary' => $active, 'opacity-40 group-hover:opacity-100' => ! $active]) />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
