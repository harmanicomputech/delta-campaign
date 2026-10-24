@php($tz = config('campaign.display_timezone'))
<div x-data x-init="@js($canRecord) && $store.sync.refreshLists()">
    <x-ui.page-header :title="$list->name" :eyebrow="__('canvassing.walk_list')">
        <x-slot:actions>
            <x-ui.badge :tone="$list->status->tone()" dot>{{ $list->status->label() }}</x-ui.badge>
            @if ($canManage && $list->status->value !== 'archived')
                <x-ui.button variant="secondary" icon="user-plus" x-on:click="$dispatch('open-modal', 'assign-walk-list')">{{ $list->assignee ? __('canvassing.reassign') : __('canvassing.assign') }}</x-ui.button>
                <x-ui.button variant="ghost" icon="archive" x-on:click="$dispatch('open-modal', 'archive-walk-list')">{{ __('canvassing.archive') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4 [&>*]:min-w-0">
        <x-ui.card class="col-span-2 sm:col-span-1">
            <p class="text-[0.8125rem] font-medium text-muted">{{ __('canvassing.progress_label') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-fg">{{ $progress['done'] }}<span class="text-base text-subtle">/{{ $progress['total'] }}</span></p>
            <x-ui.progress class="mt-3" :value="$progress['done']" :max="max(1, $progress['total'])" :tone="$progress['done'] >= $progress['total'] ? 'success' : 'brand'" :label="__('canvassing.progress', ['done' => $progress['done'], 'total' => $progress['total']])" />
        </x-ui.card>
        <x-ui.stat :label="__('canvassing.reached')" :value="number_format((int) ($outcomeCounts['reached'] ?? 0) + (int) ($outcomeCounts['registered'] ?? 0))" icon="circle-check" />
        <x-ui.stat :label="__('canvassing.follow_ups')" :value="number_format((int) ($outcomeCounts['follow_up_requested'] ?? 0))" icon="calendar" />
        <x-ui.card class="col-span-2 xl:col-span-1">
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="truncate text-right text-fg">{{ $list->ward?->name }} · {{ $list->lga?->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ __('canvassing.assignee') }}</dt><dd class="truncate text-right text-fg">{{ $list->assignee?->name ?? __('canvassing.unassigned') }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ __('canvassing.due_on') }}</dt><dd class="text-right text-fg">{{ $list->due_on?->format('j M Y') ?? '—' }}</dd></div>
            </dl>
        </x-ui.card>
    </div>

    @if ($list->notes)
        <x-ui.alert tone="info" :title="__('canvassing.notes')" class="mb-6">{{ $list->notes }}</x-ui.alert>
    @endif

    <x-ui.card :title="__('canvassing.stops_title')" :description="$canRecord ? __('canvassing.capture_help') : null" :padding="false">
        <ol class="divide-y divide-line">
            @foreach ($entries as $entry)
                @php($last = $entry->contactAttempts->first())
                <li wire:key="e-{{ $entry->id }}" x-data="{ open: false, queued: false }" class="px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span @class(['mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                            'bg-surface-sunken text-muted' => $entry->status->value === 'pending',
                            'bg-success-soft text-success' => $entry->status->value === 'visited',
                            'bg-warning-soft text-warning' => $entry->status->value === 'skipped'])>
                            @if ($entry->status->value === 'visited') <x-icon name="check" class="size-4" /> @else {{ $entry->position }} @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-fg">{{ $entry->supporter?->full_name ?? $entry->label }}</p>
                            @if ($entry->supporter) <p class="font-mono text-xs text-subtle">{{ $entry->supporter->reference }}</p> @endif
                            @if ($last)
                                <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                                    <x-ui.badge :tone="$last->outcome->tone()" size="sm">{{ $last->outcome->label() }}</x-ui.badge>
                                    @if ($last->quality_flag->value !== 'ok') <x-ui.badge :tone="$last->quality_flag->tone()" size="sm">{{ $last->quality_flag->label() }}</x-ui.badge> @endif
                                    {{ $last->user->name }} · {{ $last->occurred_at->timezone($tz)->format('j M, H:i') }}
                                </p>
                            @elseif ($entry->status->value === 'skipped')
                                <p class="mt-1 text-xs text-warning">{{ __('canvassing.skipped') }}</p>
                            @endif
                            <p x-show="queued" x-cloak class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-warning"><x-icon name="cloud-off" class="size-3.5" /> {{ __('sync.saved_on_device') }}</p>
                        </div>
                        @if ($canRecord)
                            <button type="button" x-on:click="open = !open" :aria-expanded="open" class="shrink-0 rounded-md border border-line px-2.5 py-1.5 text-xs font-medium text-muted hover:border-line-strong hover:text-fg">
                                <span x-text="open ? @js(__('ui.close')) : @js($entry->status->value === 'pending' ? __('canvassing.record') : __('canvassing.record_again'))"></span>
                            </button>
                        @endif
                    </div>

                    @if ($canRecord)
                        <div x-show="open" x-cloak x-collapse class="mt-3 pl-10">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($outcomes as $value => $outcome)
                                    <button type="button"
                                            x-on:click="$store.sync.capture(
                                                { entryId: {{ $entry->id }}, outcome: '{{ $value }}', notes: $refs.note{{ $entry->id }}.value, label: @js($entry->supporter?->full_name ?? $entry->label), listUuid: @js($list->uuid), listName: @js($list->name) },
                                                (id) => $wire.record({{ $entry->id }}, '{{ $value }}', id),
                                            ).then((result) => { open = false; queued = result === 'queued' })"
                                            wire:loading.attr="disabled" wire:target="record"
                                            @class(['flex min-h-11 items-center justify-center rounded-lg border px-3 py-2 text-sm font-medium transition-colors disabled:opacity-50',
                                                'border-success/30 bg-success-soft text-success hover:border-success' => in_array($value, ['reached', 'registered'], true),
                                                'border-danger/30 bg-danger-soft text-danger hover:border-danger' => $value === 'do_not_contact',
                                                'border-line bg-surface text-fg hover:border-line-strong' => ! in_array($value, ['reached', 'registered', 'do_not_contact'], true)])>
                                        {{ $outcome->label() }}
                                    </button>
                                @endforeach
                            </div>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-start">
                                <label class="sr-only" for="note-{{ $entry->id }}">{{ __('canvassing.note') }}</label>
                                <textarea id="note-{{ $entry->id }}" x-ref="note{{ $entry->id }}" wire:model="notes.{{ $entry->id }}" rows="1" placeholder="{{ __('canvassing.note_placeholder') }}" class="form-control flex-1"></textarea>
                                @if ($entry->status->value === 'pending')
                                    <x-ui.button variant="ghost" size="sm" icon="skip-forward" wire:click="skip({{ $entry->id }})" x-bind:disabled="! $store.network.online">{{ __('canvassing.skip') }}</x-ui.button>
                                @endif
                            </div>
                            <p class="mt-1.5 text-xs text-subtle">{{ __('canvassing.no_opinions') }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="assign-walk-list" :title="__('canvassing.assign')" :description="__('canvassing.assign_help')" icon="user-plus">
            <div class="space-y-3">
                <input type="search" wire:model.live.debounce.300ms="assigneeSearch" placeholder="{{ __('canvassing.search_people') }}" aria-label="{{ __('canvassing.search_people') }}" class="form-control">
                <div class="max-h-72 space-y-1 overflow-y-auto scroll-thin" role="radiogroup" aria-label="{{ __('canvassing.assignee') }}">
                    @forelse ($candidates as $person)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-3 py-2 text-sm hover:border-line-strong has-[:checked]:border-primary has-[:checked]:bg-primary-soft/50" wire:key="p-{{ $person->id }}">
                            <input type="radio" wire:model="assigneeId" value="{{ $person->id }}" class="size-4 accent-[var(--c-primary)]">
                            <x-ui.avatar :name="$person->name" size="sm" />
                            <span class="flex-1 text-fg">{{ $person->name }}</span>
                            <span class="font-mono text-xs text-subtle">•••• {{ $person->phone_last4 }}</span>
                        </label>
                    @empty
                        <x-ui.empty-state compact icon="users" :title="__('canvassing.no_candidates')" :description="__('canvassing.no_candidates_help')" />
                    @endforelse
                </div>
                @error('assigneeId') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                @error('assignee') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button wire:click="assign" loading="assign">{{ __('canvassing.assign') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.confirm-dialog name="archive-walk-list" :title="__('canvassing.archive_title')" :description="__('canvassing.archive_help')" tone="warning">
            <x-slot:confirm><x-ui.button variant="danger" x-on:click="open = false; $wire.archive()">{{ __('canvassing.archive') }}</x-ui.button></x-slot:confirm>
        </x-ui.confirm-dialog>
    @endif
</div>
