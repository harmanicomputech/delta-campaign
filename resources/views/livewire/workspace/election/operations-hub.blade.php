<div wire:poll.30s.visible>
    <x-ui.page-header :title="__('election.title')" :description="$election ? $election->name.' · '.$election->election_date->format('j F Y') : __('election.intro')">
        <x-slot:actions>
            @if ($election) <x-ui.badge :tone="$election->status->tone()" dot>{{ $election->status->label() }}</x-ui.badge> @endif
            @can('election.setup') <x-ui.button variant="secondary" icon="settings" :href="route('workspace.election.setup')">{{ __('election.setup') }}</x-ui.button> @endcan
            @can('evidence.export') @if ($election) <x-ui.button variant="secondary" icon="archive" :href="route('workspace.election.packs')">{{ __('election.packs') }}</x-ui.button> @endif @endcan
        </x-slot:actions>
    </x-ui.page-header>
    @if ($election === null)
        <x-ui.card><x-ui.empty-state icon="vote" :title="__('election.none')" :description="__('election.none_help')" /></x-ui.card>
    @else
        @if ($election->is_sample) <x-ui.alert tone="warning" class="mb-6">{{ __('election.sample_notice') }}</x-ui.alert> @endif
        @php($pct = fn ($n) => $summary['units'] > 0 ? round($n / $summary['units'] * 100) : 0)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat :label="__('election.stat_assigned')" :value="number_format($summary['assigned'])" :hint="__('election.of_units', ['units' => number_format($summary['units']), 'pct' => $pct($summary['assigned'])])" icon="shield-check" />
            <x-ui.stat :label="__('election.stat_arrived')" :value="number_format($summary['arrived'])" :hint="__('election.checklist_done', ['count' => number_format($summary['checklist'])])" icon="map-pin" />
            <x-ui.stat :label="__('election.stat_results')" :value="number_format($summary['results'])" :hint="__('election.verified_flagged', ['verified' => number_format($summary['verified']), 'flagged' => number_format($summary['flagged'])])" icon="file-check" />
            <x-ui.stat :label="__('election.stat_incidents')" :value="number_format($summary['incidents_open'])" :hint="__('election.critical_count', ['count' => $summary['incidents_critical']])" icon="triangle-alert" />
        </div>
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
            <x-ui.card :title="$byWard ? __('election.by_ward') : __('election.by_lga')" :padding="false">
                <x-slot:actions>
                    @can('polling-agents.view') <x-ui.button size="sm" variant="ghost" :href="route('workspace.election.agents')">{{ __('election.agents') }}</x-ui.button> @endcan
                    @can('incidents.view') <x-ui.button size="sm" variant="ghost" :href="route('workspace.election.incidents.index')">{{ __('election.incidents') }}</x-ui.button> @endcan
                    @can('results.view') <x-ui.button size="sm" variant="ghost" :href="route('workspace.election.results')">{{ __('election.results') }}</x-ui.button> @endcan
                </x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-sunken/60 text-left text-xs font-semibold tracking-wide text-muted uppercase">
                            <tr><th class="px-5 py-2.5">{{ $byWard ? __('volunteers.ward') : 'LGA' }}</th><th class="px-3 py-2.5 text-right">{{ __('election.units') }}</th><th class="px-3 py-2.5 text-right">{{ __('election.col_assigned') }}</th><th class="px-3 py-2.5 text-right">{{ __('election.col_arrived') }}</th><th class="px-3 py-2.5 text-right">{{ __('election.col_results') }}</th><th class="px-3 py-2.5 text-right">{{ __('election.col_verified') }}</th><th class="px-5 py-2.5 text-right">{{ __('election.col_incidents') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line tabular-nums">
                            @forelse ($breakdown as $row)
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-fg">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2.5 text-right text-muted">{{ number_format($row['units']) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ number_format($row['assigned']) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ number_format($row['arrived']) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ number_format($row['results']) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ number_format($row['verified']) }}</td>
                                    <td @class(['px-5 py-2.5 text-right', 'font-semibold text-danger' => $row['incidents'] > 0])>{{ number_format($row['incidents']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-6 text-center text-muted">{{ __('election.no_units') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
            @if ($verified !== null)
                <div class="space-y-6">
                    @foreach (['verified' => $verified, 'official' => $official] as $kind => $tally)
                        <x-ui.card :title="__('election.tally_'.$kind)" :description="__('election.tally_'.$kind.'_help', ['units' => number_format($tally['units']), 'total' => number_format($summary['units'])])">
                            @if ($tally['units'] === 0)
                                <p class="text-sm text-muted">{{ __('election.tally_empty') }}</p>
                            @else
                                @php($max = max(1, max($tally['scores'] ?: [0])))
                                <ul class="space-y-2.5 text-sm">
                                    @foreach ($tally['scores'] as $code => $votes)
                                        <li><div class="flex justify-between"><span class="font-medium text-fg">{{ $code }}</span><span class="tabular-nums text-fg">{{ number_format($votes) }}</span></div>
                                            <div class="mt-1 h-1.5 rounded-full bg-surface-sunken"><div class="h-1.5 rounded-full {{ $kind === 'official' ? 'bg-[var(--c-chart-2)]' : 'bg-[var(--c-chart-1)]' }}" style="width: {{ round($votes / $max * 100, 1) }}%"></div></div></li>
                                    @endforeach
                                </ul>
                            @endif
                        </x-ui.card>
                    @endforeach
                    @if ($discrepancies > 0)
                        <x-ui.alert tone="danger" :title="trans_choice('election.discrepancies', $discrepancies, ['count' => $discrepancies])">
                            <a href="{{ route('workspace.election.results', ['view' => 'discrepancies']) }}" class="font-medium underline">{{ __('election.review_discrepancies') }}</a>
                        </x-ui.alert>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
