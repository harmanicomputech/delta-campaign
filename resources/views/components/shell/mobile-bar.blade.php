{{-- Thumb-reachable bottom navigation for phones. --}}
@props(['items'])
<nav aria-label="{{ __('ui.mobile_navigation') }}" class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md lg:hidden">
    <ul class="mx-auto grid max-w-lg" style="grid-template-columns: repeat({{ count($items) + 1 }}, minmax(0, 1fr))">
        @foreach ($items as $item)
            <li>
                @if ($item->isPlanned())
                    <span class="flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] text-subtle/60" aria-disabled="true" title="{{ __('nav.planned_phase', ['phase' => $item->phase]) }}">
                        <x-icon :name="$item->icon" class="size-5" />
                        <span class="max-w-full truncate px-1">{{ $item->label }}</span>
                    </span>
                @else
                    <a href="{{ $item->url }}" @if ($item->active) aria-current="page" @endif
                       @class(['flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium', 'text-primary' => $item->active, 'text-muted' => ! $item->active])>
                        <x-icon :name="$item->icon" class="size-5" :stroke="$item->active ? 2.1 : 1.75" />
                        <span class="max-w-full truncate px-1">{{ $item->label }}</span>
                    </a>
                @endif
            </li>
        @endforeach
        <li>
            <button type="button" x-on:click="$store.sidebar.mobileOpen = true" class="flex h-16 w-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium text-muted">
                <x-icon name="menu" class="size-5" />
                <span>{{ __('nav.more') }}</span>
            </button>
        </li>
    </ul>
</nav>
