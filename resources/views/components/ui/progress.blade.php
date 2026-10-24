@props(['value' => 0, 'max' => 100, 'tone' => 'brand', 'label' => null])
@php
    $percent = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0;
    $bar = ['brand' => 'bg-primary', 'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'info' => 'bg-info'][$tone] ?? 'bg-primary';
@endphp
<div {{ $attributes->class('h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken') }} role="progressbar" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}" @if ($label) aria-label="{{ $label }}" @endif>
    <div class="h-full rounded-full {{ $bar }} transition-[width] duration-500 ease-out" style="width: {{ $percent }}%"></div>
</div>
