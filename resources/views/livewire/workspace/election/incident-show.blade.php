@php($tz = config('campaign.display_timezone'))
@php($s = $incident->status->value)
<div>
    <x-ui.page-header :title="$incident->category->label()" :eyebrow="$incident->reference">
        <x-slot:actions>
            <x-ui.badge :tone="$incident->severity->tone()">{{ $incident->severity->label() }}</x-ui.badge>
            <x-ui.badge :tone="$incident->status->tone()" dot>{{ $incident->status->label() }}</x-ui.badge>
            @if ($canManage)
                @if ($s === 'reported') <x-ui.button icon="check" wire:click="acknowledge" loading="acknowledge">{{ __('election.acknowledge') }}</x-ui.button> @endif
                @if (in_array($s, ['reported', 'acknowledged'], true)) <x-ui.button variant="danger" icon="arrow-up" wire:click="startAction('escalate')">{{ __('election.escalate') }}</x-ui.button> @endif
                @if ($incident->isOpen()) <x-ui.button variant="secondary" icon="circle-check" wire:click="startAction('resolve')">{{ __('election.resolve') }}</x-ui.button> @endif
                @if (in_array($s, ['reported', 'acknowledged'], true)) <x-ui.button variant="ghost" wire:click="startAction('dismiss')">{{ __('election.dismiss') }}</x-ui.button> @endif
                @if (! $incident->isOpen()) <x-ui.button variant="ghost" icon="refresh-cw" wire:click="startAction('reopen')">{{ __('election.reopen') }}</x-ui.button> @endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('election.what_happened')">
                <p class="text-sm whitespace-pre-line text-fg">{{ $incident->description }}</p>
                @if ($incident->resolution) <div class="mt-4 rounded-lg bg-surface-sunken p-3 text-sm"><p class="text-xs font-semibold text-muted uppercase">{{ __('election.outcome') }}</p><p class="mt-1 whitespace-pre-line text-fg">{{ $incident->resolution }}</p></div> @endif
            </x-ui.card>
            <x-ui.card :title="__('election.evidence')">
                <x-election.evidence-list :files="$incident->evidence" :allowed="$canSeeEvidence" />
                @if ($canManage)
                    <form wire:submit="upload" class="mt-4 flex flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:items-center">
                        <input type="file" wire:model="file" accept="image/*,application/pdf" aria-label="{{ __('election.add_evidence') }}" class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg">
                        <x-ui.button type="submit" size="sm" variant="secondary" icon="upload" loading="upload">{{ __('election.add_evidence') }}</x-ui.button>
                    </form>
                    @error('file') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                @endif
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card :title="__('content.details')">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('election.polling_unit') }}</dt><dd class="text-right text-fg">@if ($incident->pollingUnit)<a href="{{ route('workspace.election.units.show', $incident->pollingUnit) }}" class="hover:underline">{{ $incident->pollingUnit->code }}</a><span class="block text-xs text-muted">{{ $incident->pollingUnit->name }}</span>@else — @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="text-right text-fg">{{ collect([$incident->ward?->name, $incident->lga?->name])->filter()->implode(' · ') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('election.occurred') }}</dt><dd class="text-fg">{{ $incident->occurred_at->timezone($tz)->format('j M, H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('election.reported_by') }}</dt><dd class="text-right text-fg">{{ $incident->reporter?->name }} <span class="block text-xs text-muted">{{ strtoupper($incident->source) }}</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('rapid.assignee') }}</dt><dd class="text-fg">{{ $incident->assignee?->name ?? __('rapid.unassigned') }}</dd></div>
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('volunteers.history')" :padding="false">
                <ol class="divide-y divide-line">
                    <li class="px-5 py-2.5 text-sm"><x-ui.badge size="sm">{{ __('election.reported') }}</x-ui.badge> <span class="text-xs text-subtle">{{ $incident->created_at->timezone($tz)->format('j M, H:i') }}</span></li>
                    @foreach ($history as $step)
                        <li class="px-5 py-2.5 text-sm"><x-ui.badge size="sm">{{ \App\Enums\IncidentStatus::tryFrom($step->to_status)?->label() ?? $step->to_status }}</x-ui.badge> <span class="text-xs text-subtle">{{ $step->user?->name }} · {{ $step->created_at->timezone($tz)->format('j M, H:i') }}</span>@if ($step->note)<p class="mt-1 text-xs text-muted">{{ $step->note }}</p>@endif</li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>
    @if ($canManage)
        <x-ui.modal name="incident-action" :title="$action ? __('election.'.$action) : ''" icon="pencil">
            <form wire:submit="confirmAction" id="incident-action-form"><x-ui.textarea wire:model="note" :label="__('election.note_'.($action ?: 'resolve'))" rows="3" required /></form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="incident-action-form" loading="confirmAction">{{ __('ui.confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
