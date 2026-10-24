<div>
    <x-ui.page-header :title="__('geography.title')" :description="__('geography.intro')">
        <x-slot:actions>
            @can('geography.import')
                <x-ui.button icon="upload" :href="route('workspace.geography.imports')">{{ __('geography.imports') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 [&>*]:min-w-0">
        <x-ui.stat :label="__('geography.districts')" :value="number_format($totals['districts'])" icon="map" />
        <x-ui.stat :label="__('geography.lgas')" :value="number_format($totals['lgas'])" icon="building-2" />
        <x-ui.stat :label="__('geography.wards')" :value="number_format($totals['wards'])" icon="layers" />
        <x-ui.stat :label="__('geography.polling_units')" :value="number_format($totals['units'])" icon="map-pinned" :hint="$totals['sample_units'] > 0 ? __('geography.sample_units', ['count' => number_format($totals['sample_units'])]) : null" />
    </div>

    @if ($totals['units'] === 0)
        <x-ui.alert tone="warning" :title="__('geography.no_units_title')" class="mb-6">{{ __('geography.no_units_body') }}</x-ui.alert>
    @elseif ($totals['sample_units'] > 0)
        <x-ui.alert tone="warning" class="mb-6">{{ __('geography.sample_warning') }}</x-ui.alert>
    @endif

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 lg:flex-row lg:items-center">
            <nav aria-label="{{ __('geography.path') }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-sm">
                <button type="button" wire:click="back('root')" @class(['rounded px-1.5 py-0.5 hover:bg-surface-sunken', 'font-semibold text-fg' => ! $districtModel && ! $lgaModel, 'text-muted' => $districtModel || $lgaModel])>{{ __('geography.state') }}</button>
                @if ($districtModel)
                    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
                    <button type="button" wire:click="back('district')" @class(['rounded px-1.5 py-0.5 hover:bg-surface-sunken', 'font-semibold text-fg' => ! $lgaModel, 'text-muted' => $lgaModel])>{{ $districtModel->name }}</button>
                @endif
                @if ($lgaModel)
                    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
                    <button type="button" wire:click="back('lga')" @class(['rounded px-1.5 py-0.5 hover:bg-surface-sunken', 'font-semibold text-fg' => ! $wardModel, 'text-muted' => $wardModel])>{{ $lgaModel->name }}</button>
                @endif
                @if ($wardModel)
                    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
                    <span class="px-1.5 py-0.5 font-semibold text-fg">{{ $wardModel->name }}</span>
                @endif
            </nav>
            <div class="relative lg:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('geography.search') }}" aria-label="{{ __('geography.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
        </div>

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @if ($items->isEmpty())
                <x-ui.empty-state icon="map" :title="$level === 'search' ? __('geography.no_results') : __('geography.empty')" :description="$level === 'polling_units' || $level === 'wards' ? __('geography.empty_help') : null" />
            @elseif (in_array($level, ['search', 'polling_units'], true))
                <x-ui.table :caption="__('geography.polling_units')">
                    <x-slot:head>
                        <tr>
                            <x-ui.th>{{ __('geography.code') }}</x-ui.th>
                            <x-ui.th>{{ __('geography.name') }}</x-ui.th>
                            @if ($level === 'search') <x-ui.th class="hidden md:table-cell">{{ __('geography.ward') }}</x-ui.th> @endif
                            <x-ui.th class="hidden lg:table-cell">{{ __('geography.location') }}</x-ui.th>
                            <x-ui.th align="right" class="hidden sm:table-cell">{{ __('geography.registered_voters') }}</x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($items as $unit)
                        <tr wire:key="pu-{{ $unit->id }}">
                            <x-ui.td class="font-mono text-xs">{{ $unit->code }} @if ($unit->is_sample) <x-ui.sample-badge class="ml-1" /> @endif</x-ui.td>
                            <x-ui.td>{{ $unit->name }}</x-ui.td>
                            @if ($level === 'search') <x-ui.td class="hidden text-muted md:table-cell">{{ $unit->ward?->name }} · {{ $unit->lga?->name }}</x-ui.td> @endif
                            <x-ui.td class="hidden font-mono text-xs text-muted lg:table-cell">{{ $unit->latitude !== null ? number_format((float) $unit->latitude, 5).', '.number_format((float) $unit->longitude, 5) : '—' }}</x-ui.td>
                            <x-ui.td align="right" class="hidden tabular-nums text-muted sm:table-cell">{{ $unit->registered_voters !== null ? number_format($unit->registered_voters) : '—' }}</x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($items as $area)
                        @php($next = ['districts' => 'district', 'lgas' => 'lga', 'wards' => 'ward'][$level])
                        <li wire:key="{{ $level }}-{{ $area->id }}">
                            <button type="button" wire:click="open('{{ $next }}', {{ $area->id }})" class="flex w-full items-center gap-3 px-5 py-3 text-left transition-colors hover:bg-surface-sunken/60">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-fg">{{ $area->name }}
                                        @if ($level === 'wards') <span class="ml-1 font-mono text-xs text-subtle">{{ $area->code }}</span> @endif
                                        @if (($area->is_sample ?? false)) <x-ui.sample-badge class="ml-1" /> @endif
                                    </span>
                                    <span class="block text-xs text-muted">
                                        @if ($level === 'districts') {{ trans_choice('geography.count_lgas', $area->lgas_count, ['count' => $area->lgas_count]) }} · @endif
                                        @if (in_array($level, ['districts', 'lgas'], true)) {{ trans_choice('geography.count_wards', $area->wards_count, ['count' => $area->wards_count]) }} · @endif
                                        {{ trans_choice('geography.count_units', $area->polling_units_count, ['count' => number_format($area->polling_units_count)]) }}
                                        @if ($level === 'lgas' && $area->inec_code) · INEC {{ $area->inec_code }} @endif
                                    </span>
                                </span>
                                <x-icon name="chevron-right" class="size-4 text-subtle" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if ($items->hasPages()) <div class="border-t border-line">{{ $items->links() }}</div> @endif
    </x-ui.card>
</div>
