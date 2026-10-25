@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('rapid.title')" :description="__('rapid.intro')">
        <x-slot:actions>
            @can('rapid-response.manage') <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'rr-new')">{{ __('rapid.new') }}</x-ui.button> @endcan
        </x-slot:actions>
    </x-ui.page-header>
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('rapid.search') }}" aria-label="{{ __('rapid.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="open">{{ __('rapid.open_cases') }}</option>
                <option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        @if ($cases->isEmpty())
            <x-ui.empty-state icon="siren" :title="__('rapid.none')" :description="__('rapid.none_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($cases as $case)
                    <li wire:key="c-{{ $case->id }}">
                        <a href="{{ route('workspace.rapid-response.show', $case) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs text-subtle">{{ $case->reference }}</span><span class="font-medium text-fg">{{ $case->title }}</span></span>
                                <span class="block text-xs text-muted">{{ $case->assignee?->name ?? __('rapid.unassigned') }} · {{ $case->created_at->timezone($tz)->format('j M, H:i') }}
                                    @if ($case->deadline_at) · <span @class(['font-medium text-danger' => $case->isOverdue()])>{{ __('rapid.due', ['time' => $case->deadline_at->timezone($tz)->format('j M, H:i')]) }}</span> @endif</span>
                            </span>
                            <span class="flex items-center gap-2">
                                @if ($case->priority->value !== 'normal') <x-ui.badge :tone="$case->priority->tone()" size="sm">{{ $case->priority->label() }}</x-ui.badge> @endif
                                <x-ui.badge :tone="$case->status->tone()" size="sm" dot>{{ $case->status->label() }}</x-ui.badge>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $cases->links() }}</div>
        @endif
    </x-ui.card>
    @can('rapid-response.manage')
        <x-ui.modal name="rr-new" :title="__('rapid.new')" icon="siren" maxWidth="2xl">
            <form wire:submit="open" id="rr-new-form" class="space-y-4">
                <x-ui.input wire:model="title" :label="__('rapid.case_title')" required />
                <x-ui.textarea wire:model="claim" :label="__('rapid.claim')" :hint="__('rapid.claim_hint')" rows="4" required />
                <x-ui.textarea wire:model="sources" :label="__('rapid.sources')" :hint="__('rapid.sources_hint')" rows="2" optional />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="priority" :label="__('tasks.priority')" :options="\App\Enums\TaskPriority::options()" />
                    <x-ui.input type="datetime-local" wire:model="deadline" :label="__('rapid.deadline')" optional />
                </div>
                <x-ui.checkbox wire:model="requiresLegal" :label="__('rapid.requires_legal')" :description="__('rapid.requires_legal_help')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="rr-new-form" loading="open">{{ __('rapid.open_case') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
