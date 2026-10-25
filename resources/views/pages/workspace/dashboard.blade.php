@php
    $hour = (int) now()->timezone(config('campaign.display_timezone'))->format('G');
    $greeting = $hour < 12 ? __('workspace.good_morning') : ($hour < 17 ? __('workspace.good_afternoon') : __('workspace.good_evening'));
    $modules = collect(app(\App\Support\Navigation\Navigation::class)->groups())->flatMap(fn ($g) => $g['items'])->reject(fn ($i) => $i->url === route('workspace.dashboard'));
@endphp
<x-layouts::app :title="__('nav.dashboard')" :breadcrumbs="[__('shell.workspace') => null, __('nav.dashboard') => null]">
    <x-ui.page-header :title="$greeting.', '.\Illuminate\Support\Str::before($user->name, ' ')" :eyebrow="$user->broadestScope()?->label()"
                      :description="__('workspace.dashboard_intro')" />

    @if ($assignments->isEmpty())
        <x-ui.alert tone="warning" :title="__('workspace.no_roles_title')" class="mb-6">{{ __('workspace.no_roles_body') }}</x-ui.alert>
    @endif

    @if ($user->pin_hash === null)
        <x-ui.alert tone="info" :title="__('workspace.set_pin_title')" class="mb-6">
            {{ __('workspace.set_pin_body') }}
            <a href="{{ route('workspace.account') }}#pin" class="font-semibold text-info underline-offset-2 hover:underline">{{ __('workspace.set_pin_action') }}</a>
        </x-ui.alert>
    @endif

    @if ($agentAssignment)
        <a href="{{ route('workspace.election.agent') }}" class="mb-6 flex items-center gap-4 rounded-xl border border-primary bg-primary-soft px-5 py-4 hover:shadow-sm">
            <x-icon name="vote" class="size-6 shrink-0 text-primary" />
            <span class="min-w-0 flex-1"><span class="block font-semibold text-fg">{{ __('workspace.agent_today', ['unit' => $agentAssignment->pollingUnit->name]) }}</span>
                <span class="block text-sm text-muted">{{ $agentAssignment->pollingUnit->code }} · {{ $agentAssignment->arrived_at ? __('workspace.agent_arrived') : __('workspace.agent_not_arrived') }}</span></span>
            <x-icon name="arrow-right" class="size-5 text-primary" />
        </a>
    @endif

    @if ($work)
        <div class="mb-6 grid gap-4 lg:grid-cols-3 [&>*]:min-w-0">
            <x-ui.card :title="__('workspace.my_tasks')" :padding="false" class="lg:col-span-2">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" :href="route('workspace.tasks.index')" icon-trailing="arrow-right">{{ trans_choice('workspace.open_count', $work['open_tasks'], ['count' => $work['open_tasks']]) }}</x-ui.button>
                </x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($work['tasks'] as $task)
                        <li>
                            <a href="{{ route('workspace.tasks.show', $task) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-sunken/60">
                                <x-ui.badge :tone="$task->priority->tone()" size="sm">{{ $task->priority->label() }}</x-ui.badge>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-fg">{{ $task->title }}</span>
                                @if ($task->due_at)
                                    <span @class(['shrink-0 text-xs', 'font-semibold text-danger' => $task->isOverdue(), 'text-muted' => ! $task->isOverdue()])>{{ $task->due_at->timezone(config('campaign.display_timezone'))->format('j M, H:i') }}</span>
                                @endif
                            </a>
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="circle-check" :title="__('workspace.no_tasks')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
            <div class="space-y-4">
                @foreach ($work['lists'] as $list)
                    <a href="{{ route('workspace.canvassing.show', $list) }}" class="block rounded-xl border border-line bg-surface p-4 shadow-xs transition hover:border-line-strong">
                        <p class="text-[0.8125rem] font-medium text-muted">{{ __('workspace.walk_list') }}</p>
                        <p class="mt-0.5 truncate font-semibold text-fg">{{ $list->name }}</p>
                        <x-ui.progress class="mt-3" :value="$list->done_count" :max="max(1, $list->entries_count)" :label="__('canvassing.progress', ['done' => $list->done_count, 'total' => $list->entries_count])" />
                        <p class="mt-1.5 text-xs text-subtle">{{ __('canvassing.progress', ['done' => $list->done_count, 'total' => $list->entries_count]) }}</p>
                    </a>
                @endforeach
                @if ($work['training_total'] > 0)
                    <a href="{{ route('workspace.training.index') }}" class="block rounded-xl border border-line bg-surface p-4 shadow-xs transition hover:border-line-strong">
                        <p class="text-[0.8125rem] font-medium text-muted">{{ __('training.title') }}</p>
                        <p class="mt-0.5 font-semibold text-fg">{{ __('training.progress', ['done' => $work['training_passed'], 'total' => $work['training_total']]) }}</p>
                        <x-ui.progress class="mt-3" tone="success" :value="$work['training_passed']" :max="$work['training_total']" :label="__('training.progress', ['done' => $work['training_passed'], 'total' => $work['training_total']])" />
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if ($snapshot)
        @php($f = fn (int $v) => \App\Livewire\Workspace\CommandCentre\CommandCentre::figure($v, $maskSnapshot))
        <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 [&>*]:min-w-0">
            <x-ui.stat :label="__('command.supporters')" :value="$f($snapshot['supporters'])" icon="users" :hint="__('command.new_this_week', ['count' => $f($snapshot['new_supporters'])])" />
            <x-ui.stat :label="__('command.volunteers')" :value="$f($snapshot['volunteers'])" icon="hand-heart" :hint="__('command.pending_applications', ['count' => $f($snapshot['applications'])])" />
            <x-ui.stat :label="__('command.contacts')" :value="$f($snapshot['contacts'])" icon="door-open" />
            <x-ui.stat :label="__('command.coverage')" :value="($snapshot['wards'] > 0 ? (int) round($snapshot['covered_wards'] / $snapshot['wards'] * 100) : 0).'%'" icon="map-pinned" :hint="__('command.wards_covered', ['covered' => $snapshot['covered_wards'], 'total' => $snapshot['wards']])" />
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('workspace.your_areas')" :description="__('workspace.your_areas_help')">
                @if ($assignments->isEmpty())
                    <p class="text-sm text-muted">—</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($assignments as $assignment)
                            <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="map-pinned" /></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-fg">{{ $assignment->role->name }}</p>
                                        <p class="truncate text-xs text-subtle">{{ $assignment->scopeLabel() }}</p>
                                    </div>
                                </div>
                                @if ($assignment->role->is_privileged) <x-ui.badge tone="accent" size="sm" icon="shield-check">{{ __('workspace.privileged') }}</x-ui.badge> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('workspace.modules')" :description="__('workspace.modules_help')">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($modules as $module)
                        @if ($module->isPlanned())
                            <div class="flex items-center gap-3 rounded-lg border border-dashed border-line p-3 text-subtle" aria-disabled="true">
                                <x-icon :name="$module->icon" class="size-[1.125rem]" />
                                <span class="flex-1 text-sm">{{ $module->label }}</span>
                                <x-ui.badge size="sm">{{ __('nav.planned_phase', ['phase' => $module->phase]) }}</x-ui.badge>
                            </div>
                        @else
                            <a href="{{ $module->url }}" class="group flex items-center gap-3 rounded-lg border border-line p-3 transition-colors hover:border-line-strong hover:bg-surface-hover">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$module->icon" /></span>
                                <span class="flex-1 text-sm font-medium text-fg">{{ $module->label }}</span>
                                <x-icon name="arrow-right" class="text-subtle transition-transform group-hover:translate-x-0.5" />
                            </a>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @if ($queues !== [])
                <x-ui.card :title="__('command.needs_decision')" :padding="false">
                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" :href="route('workspace.approvals')" icon-trailing="arrow-right">{{ __('command.open_inbox') }}</x-ui.button>
                    </x-slot:actions>
                    <ul class="divide-y divide-line">
                        @foreach ($queues as $queue)
                            <li>
                                <a href="{{ $queue['url'] }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-sunken/60">
                                    <x-icon :name="$queue['icon']" class="size-4 text-subtle" />
                                    <span class="flex-1 text-sm text-fg">{{ $queue['label'] }}</span>
                                    <x-ui.badge :tone="$queue['count'] > 0 ? 'warning' : 'neutral'" size="sm">{{ number_format($queue['count']) }}</x-ui.badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @can('system.health')
                <livewire:system.health-status-panel />
            @endcan

            <x-ui.card :title="__('workspace.recent_sign_ins')" :padding="false">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" :href="route('workspace.account')" icon-trailing="arrow-right">{{ __('workspace.security') }}</x-ui.button>
                </x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($recentLogins as $event)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm text-fg">{{ $event->event->label() }}</p>
                                <p class="truncate text-xs text-subtle">{{ $event->created_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }} · {{ $event->ip }}</p>
                            </div>
                            <x-ui.badge :tone="$event->event->tone()" size="sm" dot>{{ $event->event->isSuspicious() ? __('workspace.review') : __('workspace.ok') }}</x-ui.badge>
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="clock" :title="__('workspace.no_history')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts::app>
