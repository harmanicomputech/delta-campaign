@php
    $f = fn (int $v) => \App\Livewire\Workspace\CommandCentre\CommandCentre::figure($v, $mask);
    $pct = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100) : 0;
    $levelLabel = __('command.level_'.$currentLevel);
@endphp
<div @unless (\App\Support\LowData::enabled()) wire:poll.60s.visible @endunless>
    <x-ui.page-header :title="__('command.title')" :description="__('command.intro')">
        <x-slot:actions>
            @if (\App\Support\LowData::enabled())
                <x-ui.button variant="secondary" size="sm" icon="refresh-cw" wire:click="$refresh">{{ __('command.refresh') }}</x-ui.button>
            @endif
            <span class="inline-flex items-center gap-2 text-xs text-subtle">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
                {{ __('command.live', ['time' => now()->timezone(config('campaign.display_timezone'))->format('H:i')]) }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($mask)
        <x-ui.alert tone="info" class="mb-6">{{ __('command.masked', ['n' => \App\Livewire\Workspace\CommandCentre\CommandCentre::SMALL_NUMBER]) }}</x-ui.alert>
    @endif

    @if ($election)
        @php($es = $election['summary'])
        <a href="{{ route('workspace.election.hub') }}" class="mb-6 flex flex-col gap-3 rounded-xl border border-line bg-surface px-5 py-4 shadow-xs hover:border-primary sm:flex-row sm:items-center sm:gap-6">
            <span class="flex items-center gap-2 font-semibold text-fg"><x-icon name="vote" class="size-5 text-primary" /> {{ $election['model']->name }}</span>
            <span class="grid flex-1 grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                <span><span class="font-semibold text-fg tabular-nums">{{ number_format($es['arrived']) }}</span> <span class="text-muted">{{ __('command.election_arrived', ['units' => number_format($es['units'])]) }}</span></span>
                <span><span class="font-semibold text-fg tabular-nums">{{ number_format($es['results']) }}</span> <span class="text-muted">{{ __('command.election_results') }}</span></span>
                <span><span class="font-semibold text-fg tabular-nums">{{ number_format($es['verified']) }}</span> <span class="text-muted">{{ __('command.election_verified') }}</span></span>
                <span @class(['text-danger' => $es['incidents_critical'] > 0])><span class="font-semibold tabular-nums">{{ number_format($es['incidents_open']) }}</span> <span class="{{ $es['incidents_critical'] > 0 ? '' : 'text-muted' }}">{{ __('command.election_incidents', ['critical' => $es['incidents_critical']]) }}</span></span>
            </span>
        </a>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-5 [&>*]:min-w-0">
        <x-ui.stat :label="__('command.supporters')" :value="$f($summary['supporters'])" icon="users" :hint="__('command.new_this_week', ['count' => $f($summary['new_supporters'])])" />
        <x-ui.stat :label="__('command.verified')" :value="$pct($summary['verified'], $summary['supporters']).'%'" icon="shield-check" :hint="__('command.of_supporters', ['count' => $f($summary['verified'])])" />
        <x-ui.stat :label="__('command.volunteers')" :value="$f($summary['volunteers'])" icon="hand-heart" :hint="__('command.pending_applications', ['count' => $f($summary['applications'])])" />
        <x-ui.stat :label="__('command.contacts')" :value="$f($summary['contacts'])" icon="door-open" :hint="__('command.reached_rate', ['pct' => $pct($summary['reached'], $summary['contacts'])])" />
        <x-ui.stat :label="__('command.coverage')" :value="$pct($summary['covered_wards'], $summary['wards']).'%'" icon="map-pinned" :hint="__('command.wards_covered', ['covered' => number_format($summary['covered_wards']), 'total' => number_format($summary['wards'])])" class="col-span-2 lg:col-span-1" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('command.trends')" :description="__('command.trends_help')">
                <div class="grid gap-6 md:grid-cols-2">
                    <x-charts.daily-bars :title="__('command.registrations')" :values="$trend['supporters']" :labels="$trend['labels']" tone="brand" />
                    <x-charts.daily-bars :title="__('command.field_contacts')" :values="$trend['contacts']" :labels="$trend['labels']" tone="info" />
                </div>
            </x-ui.card>

            <x-ui.card :padding="false" :title="$parentName ? __('command.breakdown_in', ['level' => $levelLabel, 'area' => $parentName]) : __('command.breakdown', ['level' => $levelLabel])" :description="$canDrill ? __('command.drill_help') : __('command.bottom_help')">
                @unless ($atTop)
                    <x-slot:actions>
                        <x-ui.button variant="secondary" size="sm" icon="arrow-up" wire:click="up">{{ __('command.up') }}</x-ui.button>
                    </x-slot:actions>
                @endunless
                @if ($rows->isEmpty())
                    <x-ui.empty-state icon="map" :title="__('command.no_areas')" />
                @else
                    <x-ui.table :caption="__('command.breakdown', ['level' => $levelLabel])">
                        <x-slot:head>
                            <tr>
                                <x-ui.th>{{ $levelLabel }}</x-ui.th>
                                <x-ui.th align="right">{{ __('command.supporters') }}</x-ui.th>
                                <x-ui.th align="right" class="hidden sm:table-cell">{{ __('command.verified') }}</x-ui.th>
                                <x-ui.th align="right">{{ __('command.volunteers') }}</x-ui.th>
                                <x-ui.th class="hidden md:table-cell">{{ __('command.coverage') }}</x-ui.th>
                                <x-ui.th align="right" class="hidden lg:table-cell">{{ __('command.contacts_short') }}</x-ui.th>
                                <x-ui.th align="right" class="hidden lg:table-cell">{{ __('command.tasks') }}</x-ui.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($rows as $row)
                            <tr wire:key="a-{{ $currentLevel }}-{{ $row['id'] }}" class="group">
                                <x-ui.td>
                                    @if ($canDrill)
                                        <button type="button" wire:click="drill({{ $row['id'] }})" class="inline-flex items-center gap-1.5 text-left font-medium text-fg hover:text-primary">
                                            {{ $row['name'] }} <x-icon name="chevron-right" class="size-3.5 text-subtle transition-transform group-hover:translate-x-0.5" />
                                        </button>
                                    @else
                                        <span class="font-medium text-fg">{{ $row['name'] }}</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td align="right" class="tabular-nums">{{ $f($row['supporters']) }}@if ($row['new_supporters'] > 0)<span class="block text-xs text-success">+{{ $f($row['new_supporters']) }}</span>@endif</x-ui.td>
                                <x-ui.td align="right" class="hidden tabular-nums text-muted sm:table-cell">{{ $pct($row['verified'], $row['supporters']) }}%</x-ui.td>
                                <x-ui.td align="right" class="tabular-nums">{{ $f($row['volunteers']) }}@if ($row['applications'] > 0)<span class="block text-xs text-warning">{{ __('command.pending_short', ['count' => $f($row['applications'])]) }}</span>@endif</x-ui.td>
                                <x-ui.td class="hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <x-ui.progress :value="$row['covered_wards']" :max="max(1, $row['wards'])" :tone="$pct($row['covered_wards'], $row['wards']) >= 50 ? 'success' : 'warning'" class="w-20" :label="__('command.wards_covered', ['covered' => $row['covered_wards'], 'total' => $row['wards']])" />
                                        <span class="text-xs tabular-nums text-muted">{{ $row['covered_wards'] }}/{{ $row['wards'] }}</span>
                                    </div>
                                </x-ui.td>
                                <x-ui.td align="right" class="hidden tabular-nums lg:table-cell">{{ $f($row['contacts']) }}</x-ui.td>
                                <x-ui.td align="right" class="hidden lg:table-cell">
                                    <span class="tabular-nums">{{ number_format($row['open_tasks']) }}</span>
                                    @if ($row['overdue_tasks'] > 0) <x-ui.badge tone="danger" size="sm" class="ml-1">{{ __('command.overdue_short', ['count' => $row['overdue_tasks']]) }}</x-ui.badge> @endif
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @can('approvals.view')
                <x-ui.card :title="__('command.needs_decision')" :padding="false">
                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" :href="route('workspace.approvals')" icon-trailing="arrow-right">{{ __('command.open_inbox') }}</x-ui.button>
                    </x-slot:actions>
                    <ul class="divide-y divide-line">
                        @forelse ($queues as $queue)
                            <li>
                                <a href="{{ $queue['url'] }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-sunken/60">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$queue['icon']" class="size-4" /></span>
                                    <span class="flex-1 text-sm text-fg">{{ $queue['label'] }}</span>
                                    <x-ui.badge :tone="$queue['count'] > 0 ? 'warning' : 'neutral'" size="sm">{{ number_format($queue['count']) }}</x-ui.badge>
                                </a>
                            </li>
                        @empty
                            <li><x-ui.empty-state compact icon="stamp" :title="__('approvals.none')" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endcan

            @can('tasks.view')
                <x-ui.card :title="__('command.escalations')" :padding="false">
                    <ul class="divide-y divide-line">
                        @forelse ($escalations as $task)
                            <li>
                                <a href="{{ route('workspace.tasks.show', $task) }}" class="block px-5 py-3 hover:bg-surface-sunken/60">
                                    <span class="flex items-center gap-2">
                                        <x-ui.badge tone="danger" size="sm">{{ __('tasks.level', ['level' => $task->escalation_level]) }}</x-ui.badge>
                                        <span class="truncate text-sm font-medium text-fg">{{ $task->title }}</span>
                                    </span>
                                    <span class="mt-0.5 block text-xs text-muted">{{ $task->areaLabel() }} · {{ $task->escalated_at?->diffForHumans() }}</span>
                                </a>
                            </li>
                        @empty
                            <li><x-ui.empty-state compact icon="circle-check" :title="__('command.no_escalations')" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endcan

            @can('system.health')
                <livewire:system.health-status-panel />
            @endcan
        </div>
    </div>
</div>
