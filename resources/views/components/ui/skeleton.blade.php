{{-- Loading placeholder. lines>0 renders text lines; otherwise size it with classes. --}}
@props(['lines' => 0])
@if ($lines > 0)
    <div {{ $attributes->class('space-y-2.5') }} aria-hidden="true">
        @for ($i = 0; $i < $lines; $i++)
            <div class="skeleton h-3" style="width: {{ $i === $lines - 1 ? 60 : 100 - ($i % 3) * 8 }}%"></div>
        @endfor
    </div>
@else
    <div {{ $attributes->class('skeleton') }} aria-hidden="true"></div>
@endif
