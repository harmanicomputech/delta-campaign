@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('tasks.title')" :description="__('tasks.intro')">
        <x-slot:actions>
            @can('tasks.manage')
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-task')">{{ __('tasks.new') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:max-w-xl [&>*]:min-w-0">
        <x-ui.stat :label="$view === 'mine' ? __('tasks.my_open') : __('tasks.open_in_area')" :value="number_format((int) $counts->open)" icon="list-checks" />
        <button type="button" wire:click="$toggle('overdue')" class="text-left" aria-pressed="{{ $overdue ? 'true' : 'false' }}">
            <x-ui.stat :label="__('tasks.overdue')" :value="number_format((int) $counts->overdue)" icon="clock" :class="$overdue ? 'ring-2 ring-danger/40' : 'transition hover:border-line-strong'" />
        </button>
    </div>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 xl:flex-row xl:items-center">
            @if (count($views) > 1)
                <div class="inline-flex shrink-0 rounded-lg border border-line bg-surface-sunken p-0.5" role="group">
                    @foreach ($views as $key => $label)
                        <button type="button" wire:click="$set('view', '{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                                @class(['rounded-md px-3 py-1.5 text-[0.8125rem] font-medium transition-colors', 'bg-surface text-fg shadow-xs' => $view === $key, 'text-subtle hover:text-fg' => $view !== $key])>{{ $label }}</button>
                    @endforeach
                </div>
            @endif
            <div class="relative flex-1 xl:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('tasks.search') }}" aria-label="{{ __('tasks.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
                <select wire:model.live="priority" aria-label="{{ __('tasks.priority') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('tasks.all_priorities') }}</option>
                    @foreach ($priorities as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            @if ($overdue)
                <x-ui.button size="sm" variant="ghost" icon="x" wire:click="$set('overdue', false)">{{ __('tasks.clear_overdue') }}</x-ui.button>
            @endif
            <div wire:loading.flex class="hidden items-center gap-2 text-xs text-subtle xl:ml-auto"><x-ui.spinner class="size-4" /> {{ __('ui.loading') }}</div>
        </div>

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @if ($tasks->isEmpty())
                <x-ui.empty-state icon="list-checks" :title="__('tasks.none')" :description="$view === 'mine' ? __('tasks.none_mine_help') : __('tasks.none_help')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($tasks as $task)
                        <li wire:key="t-{{ $task->id }}">
                            <a href="{{ route('workspace.tasks.show', $task) }}" class="flex flex-col gap-2 px-5 py-3.5 transition-colors hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.badge :tone="$task->priority->tone()" size="sm">{{ $task->priority->label() }}</x-ui.badge>
                                        <span @class(['font-medium', 'text-fg' => ! $task->status->isClosed(), 'text-muted line-through' => $task->status->value === 'cancelled', 'text-muted' => $task->status->value === 'completed'])>{{ $task->title }}</span>
                                        @if ($task->escalation_level > 0) <x-ui.badge tone="danger" size="sm" icon="triangle-alert">{{ __('tasks.escalated') }}</x-ui.badge> @endif
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted">{{ $task->areaLabel() }} · {{ $task->assignee?->name ?? __('tasks.unassigned') }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3 text-xs">
                                    @if ($task->due_at)
                                        <span @class(['inline-flex items-center gap-1', 'font-semibold text-danger' => $task->isOverdue(), 'text-muted' => ! $task->isOverdue()])>
                                            <x-icon name="clock" class="size-3.5" /> {{ $task->due_at->timezone($tz)->format('j M, H:i') }}
                                        </span>
                                    @endif
                                    <x-ui.badge :tone="$task->status->tone()" size="sm" dot>{{ $task->status->label() }}</x-ui.badge>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="border-t border-line">{{ $tasks->links() }}</div>
    </x-ui.card>

    @can('tasks.manage')
        <x-ui.modal name="new-task" :title="__('tasks.new')" :description="__('tasks.new_help')" max-width="xl" icon="list-checks">
            <form wire:submit="create" id="new-task-form" class="space-y-4">
                <x-ui.input wire:model="title" :label="__('tasks.task_title')" required />
                <x-ui.textarea wire:model="description" :label="__('tasks.description')" rows="3" optional />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="newPriority" :label="__('tasks.priority')" :options="$priorities" />
                    <x-ui.input type="datetime-local" wire:model="dueAt" :label="__('tasks.due_at')" optional />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="$statewide ? __('tasks.statewide') : __('users.choose')" :options="$lgas->all()" :required="! $statewide" :optional="$statewide" />
                    <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="$lgaId ? __('tasks.whole_lga') : __('users.choose_lga_first')" :options="$wards->all()" :disabled="$wards->isEmpty()" optional />
                </div>
                <p class="text-xs text-subtle">{{ __('tasks.area_help') }}</p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="new-task-form" loading="create">{{ __('tasks.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
