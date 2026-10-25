@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('election.agents')" :description="__('election.agents_intro')" />
    @if ($election === null)
        <x-ui.card><x-ui.empty-state icon="vote" :title="__('election.none')" :description="__('election.none_help')" /></x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('election.search_units') }}" aria-label="{{ __('election.search_units') }}" class="form-control h-9 min-h-9 pl-9">
                </div>
                <select wire:model.live="filter" aria-label="{{ __('public.filter') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('election.all_units') }}</option>
                    <option value="unassigned">{{ __('election.filter_unassigned') }}</option>
                    <option value="not_arrived">{{ __('election.filter_not_arrived') }}</option>
                    <option value="arrived">{{ __('election.filter_arrived') }}</option>
                </select>
            </div>
            @if ($units->isEmpty())
                <x-ui.empty-state icon="map-pinned" :title="__('election.no_units')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($units as $unit)
                        <li wire:key="u-{{ $unit->id }}" class="flex flex-col gap-3 px-5 py-3.5 lg:flex-row lg:items-center">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('workspace.election.units.show', $unit) }}" class="font-medium text-fg hover:text-primary hover:underline">{{ $unit->name }}</a>
                                <span class="block font-mono text-xs text-subtle">{{ $unit->code }} · {{ $unit->ward?->name }} · {{ $unit->lga?->name }}</span>
                            </div>
                            <div class="flex flex-1 flex-col gap-1.5">
                                @forelse ($unit->agentAssignments as $a)
                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="font-medium text-fg">{{ $a->user->name }}</span>
                                        <span class="font-mono text-xs text-subtle">••• {{ $a->user->phone_last4 }}</span>
                                        @if ($a->role === 'backup') <x-ui.badge size="sm">{{ __('election.backup') }}</x-ui.badge> @endif
                                        @if ($a->arrived_at) <x-ui.badge tone="success" size="sm" icon="check">{{ __('election.arrived_short', ['time' => $a->arrived_at->timezone($tz)->format('H:i')]) }}</x-ui.badge> @else <x-ui.badge tone="warning" size="sm">{{ __('election.not_arrived') }}</x-ui.badge> @endif
                                        @if ($canManage) <button type="button" wire:click="startWithdraw({{ $a->id }})" class="text-xs font-medium text-muted hover:text-danger">{{ __('election.withdraw') }}</button> @endif
                                    </div>
                                @empty
                                    <span class="text-sm text-muted">{{ __('election.no_agent') }}</span>
                                @endforelse
                            </div>
                            @if ($canManage && $election->status->value !== 'closed' && $unit->agentAssignments->count() < 2)
                                <x-ui.button size="sm" variant="secondary" icon="user-plus" wire:click="startAssign({{ $unit->id }})">{{ __('election.assign') }}</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line">{{ $units->links() }}</div>
            @endif
        </x-ui.card>
        @if ($canManage)
            <x-ui.modal name="assign-agent" :title="__('election.assign_agent')" :description="$assignUnit ? $assignUnit->code.' · '.$assignUnit->name : null" icon="user-plus">
                <form wire:submit="assign" id="assign-agent-form" class="space-y-4">
                    <x-ui.input wire:model="name" :label="__('public.full_name')" required />
                    <x-ui.phone-input name="phone" wire:model="phone" :label="__('public.phone')" :hint="__('election.assign_phone_hint')" />
                    <x-ui.select wire:model="role" :label="__('election.agent_role')" :options="['primary' => __('election.primary'), 'backup' => __('election.backup')]" />
                </form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" form="assign-agent-form" loading="assign">{{ __('election.assign') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
            <x-ui.modal name="withdraw-agent" :title="__('election.withdraw_agent')" icon="user-x" tone="danger">
                <p class="mb-4 text-sm text-muted">{{ __('election.withdraw_help') }}</p>
                <form wire:submit="withdraw" id="withdraw-agent-form"><x-ui.textarea wire:model="reason" :label="__('volunteers.reason')" rows="2" required /></form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button variant="danger" type="submit" form="withdraw-agent-form" loading="withdraw">{{ __('election.withdraw') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endif
</div>
