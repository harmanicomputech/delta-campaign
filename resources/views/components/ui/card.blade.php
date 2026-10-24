{{--
    Grouping surface. Use for related content, not as a default wrapper.
    Slots: header (replaces title/description), actions, footer. padding=false for edge-to-edge tables.
--}}
@props(['title' => null, 'description' => null, 'padding' => true])
<section {{ $attributes->class('overflow-hidden rounded-xl border border-line bg-surface shadow-xs') }}>
    @if (isset($header) || $title)
        <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-b border-line px-5 py-4 sm:flex-nowrap">
            @isset($header)
                {{ $header }}
            @else
                <div class="min-w-0 flex-1">
                    <h2 class="text-[0.9375rem] font-semibold tracking-tight text-fg">{{ $title }}</h2>
                    @if ($description) <p class="mt-0.5 text-[0.8125rem] text-muted">{{ $description }}</p> @endif
                </div>
            @endisset
            @isset($actions) <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div> @endisset
        </header>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
    @isset($footer)
        <footer class="border-t border-line bg-surface-sunken/50 px-5 py-3">{{ $footer }}</footer>
    @endisset
</section>
