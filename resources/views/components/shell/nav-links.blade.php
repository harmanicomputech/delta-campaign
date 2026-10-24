{{-- Navigation groups shared by the desktop sidebar and the mobile drawer. --}}
@props(['groups', 'collapsible' => false])
<nav aria-label="{{ __('ui.main_navigation') }}" class="space-y-6">
    @foreach ($groups as $group)
        <div>
            <p @class(['mb-1.5 px-3 text-[0.6875rem] font-semibold tracking-[0.08em] text-nav-muted/80 uppercase', 'sidebar-collapsed:hidden' => $collapsible])>{{ $group['label'] }}</p>
            @if ($collapsible) <div class="mx-3 mb-2 hidden border-t border-nav-line sidebar-collapsed:block" aria-hidden="true"></div> @endif
            <ul class="space-y-0.5">
                @foreach ($group['items'] as $item)
                    <li>
                        @if ($item->isPlanned())
                            <span @class(['flex h-9 cursor-default items-center gap-3 rounded-lg px-3 text-[0.8125rem] text-nav-muted/60', 'sidebar-collapsed:justify-center sidebar-collapsed:px-0' => $collapsible])
                                  title="{{ $item->label }} — {{ __('nav.planned_phase', ['phase' => $item->phase]) }}" aria-disabled="true">
                                <x-icon :name="$item->icon" class="size-[1.125rem] opacity-60" />
                                <span @class(['flex flex-1 items-center justify-between gap-2 truncate', 'sidebar-collapsed:hidden' => $collapsible])>
                                    {{ $item->label }}
                                    <span class="rounded bg-white/5 px-1.5 py-px text-[0.625rem] font-medium tracking-wide text-nav-muted/70">P{{ $item->phase }}</span>
                                </span>
                                <span class="sr-only">— {{ __('nav.planned_phase', ['phase' => $item->phase]) }}</span>
                            </span>
                        @else
                            <a href="{{ $item->url }}" @if ($item->active) aria-current="page" @endif
                               @class(['group relative flex h-9 items-center gap-3 rounded-lg px-3 text-[0.8125rem] font-medium transition-colors',
                                       'bg-nav-active text-white' => $item->active,
                                       'text-nav-fg hover:bg-nav-hover hover:text-white' => ! $item->active,
                                       'sidebar-collapsed:justify-center sidebar-collapsed:px-0' => $collapsible])
                               @if ($collapsible) x-bind:title="$store.sidebar.collapsed ? @js($item->label) : null" @endif>
                                @if ($item->active) <span class="absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-r-full bg-accent-400" aria-hidden="true"></span> @endif
                                <x-icon :name="$item->icon" @class(['size-[1.125rem]', 'text-accent-300' => $item->active, 'text-nav-muted group-hover:text-nav-fg' => ! $item->active]) />
                                <span @class(['truncate', 'sidebar-collapsed:sr-only' => $collapsible])>{{ $item->label }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
