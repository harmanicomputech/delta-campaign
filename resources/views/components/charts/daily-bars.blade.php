{{--
    Single-series daily bar chart (one measure, one axis). Hover or focus a day for its value;
    the same data is available as a table. values/labels are parallel lists; labels are Y-m-d dates.
    tone: brand | info (series colour only; text always uses text tokens).
--}}
@props(['title', 'values' => [], 'labels' => [], 'tone' => 'brand', 'unit' => ''])
@php
    $count = max(1, count($values));
    $max = max(1, ...$values ?: [1]);
    $w = 300; $h = 96; $slot = $w / $count; $bar = max(2, $slot - 2); $r = min(4, $bar / 2);
    $fill = ['brand' => 'var(--c-chart-1)', 'info' => 'var(--c-chart-2)'][$tone] ?? 'var(--c-chart-1)';
    $format = fn (string $d) => \Illuminate\Support\Carbon::parse($d)->format('j M');
    $total = array_sum($values);
    $id = 'chart-'.\Illuminate\Support\Str::random(5);
@endphp
<figure x-data="{ i: null }" {{ $attributes->class('min-w-0') }}>
    <figcaption class="flex items-baseline justify-between gap-3">
        <span class="text-[0.8125rem] font-medium text-muted">{{ $title }}</span>
        <span class="text-xs text-subtle tabular-nums" aria-live="polite">
            <template x-if="i === null"><span>{{ __('command.total', ['count' => number_format($total)]) }}</span></template>
            <template x-if="i !== null"><span><span class="font-semibold text-fg" x-text="@js($values)[i].toLocaleString()"></span> · <span x-text="@js(array_map($format, $labels))[i]"></span></span></template>
        </span>
    </figcaption>
    <svg viewBox="0 0 {{ $w }} {{ $h + 1 }}" class="mt-3 h-24 w-full overflow-visible" preserveAspectRatio="none" role="img" aria-labelledby="{{ $id }}-desc" x-on:mouseleave="i = null">
        <desc id="{{ $id }}-desc">{{ $title }}: {{ __('command.total', ['count' => number_format($total)]) }}</desc>
        @foreach ([0.5, 1] as $g)
            <line x1="0" x2="{{ $w }}" y1="{{ $h - $h * $g }}" y2="{{ $h - $h * $g }}" stroke="var(--c-line)" stroke-width="1" vector-effect="non-scaling-stroke" stroke-dasharray="2 3" />
        @endforeach
        <line x1="0" x2="{{ $w }}" y1="{{ $h }}" y2="{{ $h }}" stroke="var(--c-line-strong)" stroke-width="1" vector-effect="non-scaling-stroke" />
        @foreach ($values as $n => $value)
            @php
                $x = $n * $slot + 1; $bh = $value > 0 ? max(2, $value / $max * ($h - 4)) : 0; $y = $h - $bh; $rr = min($r, $bh);
            @endphp
            @if ($bh > 0)
                <path d="M{{ $x }},{{ $h }} V{{ $y + $rr }} Q{{ $x }},{{ $y }} {{ $x + $rr }},{{ $y }} H{{ $x + $bar - $rr }} Q{{ $x + $bar }},{{ $y }} {{ $x + $bar }},{{ $y + $rr }} V{{ $h }} Z"
                      fill="{{ $fill }}" :opacity="i === null || i === {{ $n }} ? 1 : 0.35" class="transition-opacity" />
            @endif
            <rect x="{{ $n * $slot }}" y="0" width="{{ $slot }}" height="{{ $h }}" fill="transparent" x-on:mouseenter="i = {{ $n }}" x-on:touchstart.passive="i = {{ $n }}">
                <title>{{ $format($labels[$n] ?? '') }}: {{ number_format($value) }}</title>
            </rect>
        @endforeach
    </svg>
    <div class="mt-1.5 flex justify-between text-[0.6875rem] text-subtle tabular-nums" aria-hidden="true">
        <span>{{ $labels ? $format($labels[0]) : '' }}</span>
        <span>{{ __('command.peak', ['count' => number_format($max)]) }}</span>
        <span>{{ $labels ? $format(end($labels)) : '' }}</span>
    </div>
    <details class="mt-2 text-xs">
        <summary class="cursor-pointer text-subtle hover:text-fg">{{ __('command.show_table') }}</summary>
        <div class="mt-2 max-h-48 overflow-y-auto scroll-thin">
            <table class="w-full text-left">
                <caption class="sr-only">{{ $title }}</caption>
                <thead><tr class="text-subtle"><th scope="col" class="py-1 font-medium">{{ __('command.day') }}</th><th scope="col" class="py-1 text-right font-medium">{{ __('command.count') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($values as $n => $value)
                        <tr><td class="py-1 text-muted">{{ $format($labels[$n]) }}</td><td class="py-1 text-right tabular-nums text-fg">{{ number_format($value) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
