{{-- tone: info | success | warning | danger | neutral --}}
@props(['tone' => 'info', 'title' => null, 'icon' => null, 'dismissible' => false])
@php
    $tones = [
        'info' => ['bg-info-soft border-info/20', 'text-info', 'info'],
        'success' => ['bg-success-soft border-success/20', 'text-success', 'circle-check'],
        'warning' => ['bg-warning-soft border-warning/25', 'text-warning', 'triangle-alert'],
        'danger' => ['bg-danger-soft border-danger/20', 'text-danger', 'circle-alert'],
        'neutral' => ['bg-surface-sunken border-line', 'text-muted', 'info'],
    ];
    [$box, $accent, $defaultIcon] = $tones[$tone] ?? $tones['info'];
@endphp
<div {{ $attributes->class(['flex gap-3 rounded-lg border px-4 py-3', $box]) }}
     role="{{ in_array($tone, ['danger', 'warning']) ? 'alert' : 'status' }}"
     @if ($dismissible) x-data="{ shown: true }" x-show="shown" x-transition.opacity @endif>
    <x-icon :name="$icon ?? $defaultIcon" :class="'mt-0.5 size-[1.125rem] '.$accent" />
    <div class="min-w-0 flex-1 text-sm">
        @if ($title) <p class="font-semibold text-fg">{{ $title }}</p> @endif
        <div @class(['text-muted', 'mt-0.5' => $title])>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" class="-m-1 rounded p-1 text-subtle hover:text-fg" x-on:click="shown = false">
            <x-icon name="x" /><span class="sr-only">{{ __('ui.dismiss') }}</span>
        </button>
    @endif
</div>
