<div>
    <x-ui.page-header :title="__('projects.title')" :description="__('projects.intro')">
        <x-slot:actions>
            @can('projects.manage') <x-ui.button icon="plus" :href="route('workspace.projects.create')">{{ __('projects.new') }}</x-ui.button> @endcan
        </x-slot:actions>
    </x-ui.page-header>
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('projects.search') }}" aria-label="{{ __('projects.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        @if ($projects->isEmpty())
            <x-ui.empty-state icon="building-2" :title="__('projects.none')" :description="__('projects.none_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($projects as $project)
                    <li wire:key="p-{{ $project->id }}">
                        <a href="{{ route('workspace.projects.show', $project) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-medium text-fg">{{ $project->title }}</span>@if ($project->is_sample) <x-ui.sample-badge /> @endif</span>
                                <span class="block text-xs text-muted">{{ __('projects.category_'.$project->category) }} · {{ $project->lga?->name ?? __('tasks.statewide') }} · {{ $project->creator?->name }}</span>
                            </span>
                            <x-ui.badge :tone="$project->status->tone()" size="sm" dot>{{ $project->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $projects->links() }}</div>
        @endif
    </x-ui.card>
</div>
