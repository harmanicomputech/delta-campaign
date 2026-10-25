@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('content.title')" :description="__('content.intro')">
        <x-slot:actions>
            @can('projects.manage')
                <x-ui.button variant="secondary" icon="building-2" :href="route('workspace.projects.index')">{{ __('content.projects') }}</x-ui.button>
            @endcan
            @can('content.create')
                <x-ui.button icon="plus" :href="route('workspace.content.create', ['type' => $type])">{{ __('content.new_'.$type) }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($reviewCount > 0)
        @can('content.review')
            <x-ui.alert tone="warning" class="mb-6">
                {{ trans_choice('content.awaiting_review', $reviewCount, ['count' => $reviewCount]) }}
                <button type="button" wire:click="$set('status', 'in_review')" class="ml-1 font-semibold underline">{{ __('content.show_them') }}</button>
            </x-ui.alert>
        @endcan
    @endif

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 lg:flex-row lg:items-center">
            <div class="inline-flex shrink-0 rounded-lg border border-line bg-surface-sunken p-0.5" role="group">
                @foreach ($types as $value => $label)
                    <button type="button" wire:click="$set('type', '{{ $value }}')" aria-pressed="{{ $type === $value ? 'true' : 'false' }}"
                            @class(['rounded-md px-3 py-1.5 text-[0.8125rem] font-medium', 'bg-surface text-fg shadow-xs' => $type === $value, 'text-subtle hover:text-fg' => $type !== $value])>{{ __('content.type_'.$value) }}</button>
                @endforeach
            </div>
            <div class="relative flex-1 lg:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('content.search') }}" aria-label="{{ __('content.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 lg:w-auto lg:pr-8">
                <option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        @if ($items->isEmpty())
            <x-ui.empty-state icon="newspaper" :title="__('content.none')" :description="__('content.none_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($items as $item)
                    <li wire:key="ci-{{ $item->id }}">
                        <a href="{{ route('workspace.content.show', $item) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-fg">{{ $item->title_en }}</span>
                                    @if ($item->isLive()) <x-ui.badge tone="success" size="sm" icon="globe">{{ __('content.live') }}</x-ui.badge> @endif
                                    @if ($item->is_sample) <x-ui.sample-badge /> @endif
                                </span>
                                <span class="block text-xs text-muted">/{{ $item->slug }} · {{ $item->creator?->name }} · {{ $item->updated_at->timezone($tz)->format('j M Y, H:i') }} · v{{ $item->version }}</span>
                            </span>
                            <x-ui.badge :tone="$item->status->tone()" size="sm" dot>{{ $item->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $items->links() }}</div>
        @endif
    </x-ui.card>
</div>
