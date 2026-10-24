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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
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
