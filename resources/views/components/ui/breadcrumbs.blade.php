{{-- items: ['Label' => url|null, ...]; the last item is the current page. --}}
@props(['items' => []])
<nav aria-label="{{ __('ui.breadcrumbs') }}" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1 text-[0.8125rem] text-subtle">
        @foreach ($items as $label => $url)
            <li class="flex items-center gap-1">
                @if (! $loop->first) <x-icon name="chevron-right" class="size-3.5 text-line-strong" /> @endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="rounded px-0.5 hover:text-fg">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" class="font-medium text-muted" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
