@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('events.title')" :description="__('events.intro')">
        <x-slot:actions>
            @can('events.manage')
                <x-ui.button variant="secondary" icon="scan-line" :href="route('workspace.events.check-in')">{{ __('events.check_in') }}</x-ui.button>
                <x-ui.button icon="plus" :href="route('workspace.events.create')">{{ __('events.new') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('events.search') }}" aria-label="{{ __('events.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="when" aria-label="{{ __('events.when') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="upcoming">{{ __('events.upcoming') }}</option>
                <option value="past">{{ __('events.past') }}</option>
                <option value="all">{{ __('events.all') }}</option>
            </select>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        @if ($events->isEmpty())
            <x-ui.empty-state icon="calendar-days" :title="__('events.none')" :description="__('events.none_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($events as $event)
                    @php($start = $event->starts_at->timezone($tz))
                    <li wire:key="e-{{ $event->id }}">
                        <a href="{{ route('workspace.events.show', $event) }}" class="flex items-center gap-4 px-5 py-3.5 hover:bg-surface-sunken/60">
                            <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                <span class="text-[0.65rem] font-semibold uppercase">{{ $start->format('M') }}</span><span class="text-lg leading-none font-semibold">{{ $start->format('j') }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-medium text-fg">{{ $event->title_en }}</span>@if ($event->is_sample) <x-ui.sample-badge /> @endif</span>
                                <span class="block truncate text-xs text-muted">{{ $start->format('D, g:ia') }} · {{ $event->venue }} · {{ collect([$event->ward?->name, $event->lga?->name])->filter()->implode(', ') ?: __('tasks.statewide') }}</span>
                            </span>
                            <span class="hidden text-right text-xs text-muted sm:block">{{ trans_choice('events.registered_count', $event->registered_count, ['count' => number_format($event->registered_count)]) }}@if ($event->capacity) <span class="block">{{ __('events.of_capacity', ['capacity' => number_format($event->capacity)]) }}</span>@endif</span>
                            <x-ui.badge :tone="$event->status->tone()" size="sm" dot>{{ $event->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $events->links() }}</div>
        @endif
    </x-ui.card>
</div>
