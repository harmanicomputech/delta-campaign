<div>
    <x-ui.page-header :title="__('election.setup')" :description="__('election.setup_intro')">
        <x-slot:actions>
            @if ($current)
                <x-ui.badge :tone="$current->status->tone()" dot>{{ $current->status->label() }}</x-ui.badge>
                @if ($current->status->value !== 'active') <x-ui.button icon="play" wire:click="activate" wire:confirm="{{ __('election.activate_confirm') }}" loading="activate">{{ __('election.activate') }}</x-ui.button> @endif
                @if ($current->status->value === 'active') <x-ui.button variant="danger" wire:click="close" wire:confirm="{{ __('election.close_confirm') }}" loading="close">{{ __('election.close') }}</x-ui.button> @endif
            @endif
            <x-ui.button variant="secondary" icon="plus" wire:click="create">{{ __('election.new') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px] [&>*]:min-w-0">
        <form wire:submit="save">
            <x-ui.card>
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input wire:model="name" :label="__('election.election_name')" required />
                        <x-ui.input type="date" wire:model="date" :label="__('election.election_date')" required />
                    </div>
                    <x-ui.textarea name="parties" wire:model="parties" :label="__('election.parties')" :hint="__('election.parties_hint')" rows="6" class="font-mono" />
                    <x-ui.alert tone="warning">{{ __('election.parties_warning') }}</x-ui.alert>
                    <x-ui.textarea name="checklist" wire:model="checklist" :label="__('election.checklist')" :hint="__('election.checklist_hint')" rows="7" required />
                    <x-ui.input wire:model="turnoutTimes" :label="__('election.turnout_times')" :hint="__('election.turnout_times_hint')" optional />
                </div>
                <x-slot:footer><x-ui.button type="submit" loading="save">{{ __('ui.save') }}</x-ui.button></x-slot:footer>
            </x-ui.card>
        </form>
        <x-ui.card :title="__('election.all_elections')" :padding="false">
            <ul class="divide-y divide-line">
                @forelse ($elections as $e)
                    <li><button type="button" wire:click="edit({{ $e->id }})" @class(['flex w-full items-center justify-between gap-2 px-5 py-3 text-left text-sm hover:bg-surface-sunken/60', 'bg-primary-soft' => $current?->id === $e->id])>
                        <span><span class="block font-medium text-fg">{{ $e->name }}</span><span class="text-xs text-muted">{{ $e->election_date->format('j M Y') }} · {{ trans_choice('election.party_count', $e->parties_count, ['count' => $e->parties_count]) }}</span></span>
                        <x-ui.badge :tone="$e->status->tone()" size="sm">{{ $e->status->label() }}</x-ui.badge></button></li>
                @empty
                    <li class="px-5 py-4 text-sm text-muted">{{ __('election.none') }}</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
