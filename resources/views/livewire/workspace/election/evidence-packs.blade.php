@php($tz = config('campaign.display_timezone'))
<div @if ($polling) wire:poll.3s @endif>
    <x-ui.page-header :title="__('election.packs')" :description="__('election.packs_intro')" />
    <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)] [&>*]:min-w-0">
        <x-ui.card :title="__('election.new_pack')">
            <form wire:submit="request" class="space-y-4">
                <x-ui.select wire:model.live="scopeType" name="scopeType" :label="__('election.pack_scope')" :options="array_filter(['state' => $statewide ? __('election.scope_state') : null, 'lga' => 'LGA', 'ward' => __('volunteers.ward'), 'polling_unit' => __('election.polling_unit')])" />
                @if (in_array($scopeType, ['lga', 'ward'], true)) <x-ui.select wire:model.live="lgaId" name="lgaId" label="LGA" :placeholder="__('election.choose')" :options="$lgas->all()" /> @endif
                @if ($scopeType === 'ward') <x-ui.select wire:model="wardId" name="wardId" :label="__('volunteers.ward')" :placeholder="__('election.choose')" :options="$wards->all()" :disabled="$wards->isEmpty()" /> @endif
                @if ($scopeType === 'polling_unit') <x-ui.input wire:model="unitCode" name="unitCode" :label="__('election.unit_code')" class="font-mono" /> @endif
                <x-ui.button type="submit" icon="archive" loading="request">{{ __('election.build_pack') }}</x-ui.button>
                <p class="text-xs text-subtle">{{ __('election.pack_help') }}</p>
            </form>
        </x-ui.card>
        <x-ui.card :title="__('election.my_packs')" :padding="false">
            <ul class="divide-y divide-line">
                @forelse ($packs as $pack)
                    <li class="flex flex-col gap-2 px-5 py-3 text-sm sm:flex-row sm:items-center">
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-fg">{{ __('election.scope_'.$pack->scope_type) }}@if ($pack->scope_id) #{{ $pack->scope_id }}@endif · {{ $pack->created_at->timezone($tz)->format('j M, H:i') }}</span>
                            @if ($pack->sha256) <span class="block font-mono text-xs break-all text-subtle">SHA-256 {{ $pack->sha256 }}</span> @endif
                            @if ($pack->status === 'ready') <span class="text-xs text-muted">{{ trans_choice('election.pack_files', $pack->file_count, ['count' => $pack->file_count]) }} · {{ __('election.pack_expires', ['time' => $pack->expires_at?->timezone($tz)->format('j M, H:i')]) }}</span> @endif
                            @if ($pack->error) <span class="block text-xs text-danger">{{ $pack->error }}</span> @endif
                        </span>
                        @if ($pack->status === 'ready')
                            <x-ui.button size="sm" variant="secondary" icon="download" :href="$links[$pack->id]">{{ __('election.download') }}</x-ui.button>
                        @else
                            <x-ui.badge size="sm" :tone="$pack->status === 'failed' ? 'danger' : 'info'">{{ __('election.pack_'.$pack->status) }}</x-ui.badge>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-muted">{{ __('election.no_packs') }}</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
