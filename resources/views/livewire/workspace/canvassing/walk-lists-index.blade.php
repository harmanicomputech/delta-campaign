@php($tz = config('campaign.display_timezone'))
<div x-data x-init="@js(auth()->user()->can('canvassing.record')) && $store.sync.refreshLists()">
    <x-ui.page-header :title="__('canvassing.title')" :description="__('canvassing.intro')">
        <x-slot:actions>
            @can('canvassing.manage')
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-walk-list')">{{ __('canvassing.new') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @can('canvassing.record')
        <x-ui.alert tone="info" icon="cloud-off" class="mb-6" x-show="$store.sync.supported">{{ __('sync.offline_ready') }}</x-ui.alert>
    @endcan

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            @if (count($views) > 1)
                <div class="inline-flex shrink-0 overflow-x-auto rounded-lg border border-line bg-surface-sunken p-0.5" role="group">
                    @foreach ($views as $key => $label)
                        <button type="button" wire:click="$set('view', '{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                                @class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-3 py-1.5 text-[0.8125rem] font-medium transition-colors', 'bg-surface text-fg shadow-xs' => $view === $key, 'text-subtle hover:text-fg' => $view !== $key])>
                            {{ $label }}
                            @if ($key === 'review' && $flaggedCount > 0) <span class="rounded-full bg-warning px-1.5 text-[0.6875rem] font-semibold text-white tabular-nums">{{ $flaggedCount }}</span> @endif
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($view !== 'review')
                <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_statuses') }}</option>
                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            @endif
            <div wire:loading.flex class="hidden items-center gap-2 text-xs text-subtle sm:ml-auto"><x-ui.spinner class="size-4" /> {{ __('ui.loading') }}</div>
        </div>

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @if ($view === 'review')
                @if ($flagged->isEmpty())
                    <x-ui.empty-state icon="circle-check" :title="__('canvassing.review_empty')" :description="__('canvassing.review_empty_help')" />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($flagged as $attempt)
                            <li class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center" wire:key="f-{{ $attempt->id }}">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.badge :tone="$attempt->quality_flag->tone()" size="sm" dot>{{ $attempt->quality_flag->label() }}</x-ui.badge>
                                        <x-ui.badge :tone="$attempt->outcome->tone()" size="sm">{{ $attempt->outcome->label() }}</x-ui.badge>
                                        @if ($attempt->recorded_offline) <x-ui.badge size="sm" icon="cloud-off">{{ __('canvassing.offline') }}</x-ui.badge> @endif
                                    </div>
                                    <p class="mt-1.5 text-sm text-fg">
                                        @if ($attempt->supporter) {{ $attempt->supporter->full_name }} <span class="font-mono text-xs text-subtle">{{ $attempt->supporter->reference }}</span> @else {{ $attempt->walkListEntry?->label }} @endif
                                    </p>
                                    <p class="text-xs text-muted">{{ $attempt->user->name }} · {{ $attempt->occurred_at->timezone($tz)->format('j M Y, H:i') }}@if ($attempt->walkListEntry?->walkList) · <a class="text-primary hover:underline" href="{{ route('workspace.canvassing.show', $attempt->walkListEntry->walkList) }}">{{ $attempt->walkListEntry->walkList->name }}</a>@endif</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <x-ui.button size="sm" variant="secondary" icon="check" wire:click="review({{ $attempt->id }}, 'ok')">{{ __('canvassing.mark_valid') }}</x-ui.button>
                                    <x-ui.button size="sm" variant="ghost" icon="copy" wire:click="review({{ $attempt->id }}, 'suspect_duplicate')">{{ __('canvassing.confirm_duplicate') }}</x-ui.button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-line">{{ $flagged->links() }}</div>
                @endif
            @elseif ($lists->isEmpty())
                <x-ui.empty-state icon="map-pinned" :title="$view === 'mine' ? __('canvassing.none_mine') : __('canvassing.none')" :description="$view === 'mine' ? __('canvassing.none_mine_help') : __('canvassing.none_help')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($lists as $list)
                        <li wire:key="l-{{ $list->id }}">
                            <a href="{{ route('workspace.canvassing.show', $list) }}" class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-surface-sunken/60 sm:flex-row sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-fg">{{ $list->name }}</span>
                                        <x-ui.badge :tone="$list->status->tone()" size="sm" dot>{{ $list->status->label() }}</x-ui.badge>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted">{{ $list->ward?->name }} · {{ $list->lga?->name }} · {{ $list->assignee?->name ?? __('canvassing.unassigned') }}@if ($list->due_on) · {{ __('canvassing.due', ['date' => $list->due_on->format('j M')]) }}@endif</p>
                                </div>
                                <div class="flex items-center gap-3 sm:w-56">
                                    <x-ui.progress :value="$list->done_count" :max="max(1, $list->entries_count)" :tone="$list->done_count >= $list->entries_count && $list->entries_count > 0 ? 'success' : 'brand'" class="flex-1" :label="__('canvassing.progress', ['done' => $list->done_count, 'total' => $list->entries_count])" />
                                    <span class="w-14 text-right text-xs tabular-nums text-muted">{{ $list->done_count }}/{{ $list->entries_count }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line">{{ $lists->links() }}</div>
            @endif
        </div>
    </x-ui.card>

    @can('canvassing.manage')
        <x-ui.modal name="new-walk-list" :title="__('canvassing.new')" :description="__('canvassing.new_help')" max-width="xl" icon="map-pinned">
            <form wire:submit="create" id="new-walk-list-form" class="space-y-4">
                <x-ui.input wire:model="name" :label="__('canvassing.name')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="__('users.choose')" :options="$lgas->all()" required />
                    <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="$lgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$wards->all()" :disabled="$wards->isEmpty()" required />
                </div>
                <x-ui.textarea wire:model="stops" :label="__('canvassing.stops')" :hint="__('canvassing.stops_hint')" rows="5" />
                <x-ui.checkbox wire:model="includeSupporters" :label="__('canvassing.include_supporters')" :description="__('canvassing.include_supporters_help')" />
                @error('labels') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input type="date" wire:model="dueOn" :label="__('canvassing.due_on')" optional />
                </div>
                <x-ui.textarea wire:model="notes" :label="__('canvassing.notes')" rows="2" optional />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="new-walk-list-form" loading="create">{{ __('canvassing.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
