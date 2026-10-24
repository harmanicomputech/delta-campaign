{{-- Area fields for a role's scope level. Options are pre-filtered to the actor's scope. --}}
@props(['type' => null, 'districts' => [], 'lgas' => [], 'wards' => [], 'lgaId' => null])
@if ($type === 'district')
    <x-ui.select wire:model.live="districtId" :label="__('users.district')" :placeholder="__('users.choose')" :options="$districts" required />
@elseif (in_array($type, ['lga', 'ward'], true))
    <x-ui.select wire:model.live="lgaId" :label="__('users.lga')" :placeholder="__('users.choose')" :options="$lgas" required />
    @if ($type === 'ward')
        <x-ui.select wire:model.live="wardId" :label="__('users.ward')" :placeholder="$lgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$wards" :disabled="empty($wards)" required
                     :hint="($lgaId) && empty($wards) ? __('users.no_wards') : null" />
    @endif
@elseif ($type === 'polling_unit')
    <x-ui.input wire:model.blur="pollingUnitCode" :label="__('users.polling_unit_code')" :hint="__('users.polling_unit_hint')" required />
@elseif ($type === 'state')
    <p class="rounded-lg bg-surface-sunken px-3 py-2.5 text-sm text-muted">{{ __('users.statewide_role') }}</p>
@endif
