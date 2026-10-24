{{--
    Metric tile. Values must come from real data; pass :sample="true" for illustrative figures,
    which adds a visible SAMPLE marker. "points" draws a sparkline (list of numbers).
--}}
@props(['label', 'value' => null, 'hint' => null, 'icon' => null, 'delta' => null, 'deltaTone' => 'success', 'sample' => false, 'points' => []])
@php
    $sparkline = null;
    if (count($points) > 1) {
        $min = min($points); $max = max($points); $range = max($max - $min, 1); $step = 100 / (count($points) - 1);
        $coords = collect($points)->map(fn ($p, $i) => round($i * $step, 2).','.round(28 - (($p - $min) / $range) * 24, 2));
        $sparkline = ['line' => $coords->implode(' '), 'area' => '0,30 '.$coords->implode(' ').' 100,30'];
    }
    $deltaTones = ['success' => 'text-success', 'danger' => 'text-danger', 'neutral' => 'text-subtle'];
@endphp
<div {{ $attributes->class('relative flex flex-col rounded-xl border border-line bg-surface p-5 shadow-xs') }}>
    <div class="flex items-start justify-between gap-3">
        <p class="min-w-0 text-[0.8125rem] font-medium text-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-4" /></span>
        @endif
    </div>
    <div class="mt-2 flex items-baseline gap-2">
        @if ($value === null)
            <span class="text-2xl font-semibold tracking-tight text-subtle">—</span>
        @else
            <span class="tabular text-[1.75rem] leading-8 font-semibold tracking-tight text-fg">{{ $value }}</span>
        @endif
        @if ($delta) <span class="tabular text-xs font-semibold {{ $deltaTones[$deltaTone] ?? $deltaTones['neutral'] }}">{{ $delta }}</span> @endif
    </div>
    @if ($sample) <div class="mt-1.5"><x-ui.sample-badge /></div> @endif
    @if ($hint) <p class="mt-1 text-xs text-subtle">{{ $hint }}</p> @endif
    @if ($sparkline)
        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-4 h-10 w-full text-primary" aria-hidden="true">
            <polygon points="{{ $sparkline['area'] }}" class="fill-current opacity-10" />
            <polyline points="{{ $sparkline['line'] }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
        </svg>
    @endif
</div>
