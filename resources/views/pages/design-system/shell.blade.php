<x-layouts::app :title="__('nav.dashboard')" :breadcrumbs="[__('shell.workspace') => null, __('nav.dashboard') => null]">
    <x-ui.page-header :title="__('nav.dashboard')" eyebrow="Statewide · Preview"
                      description="Operational overview. Each tile fills with live, scope-filtered data as its module is delivered.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="palette" :href="route('design-system.index')">{{ __('nav.design_system') }}</x-ui.button>
            <x-ui.button icon="search" x-on:click="$dispatch('open-command-palette')">{{ __('ui.search') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" :title="__('ui.preview')" class="mb-6" dismissible>{{ __('shell.preview_banner') }}</x-ui.alert>

    {{-- No module data exists yet: tiles show an explicit "no data" state, never invented figures. --}}
    <section aria-label="Key metrics" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Registered supporters" icon="users" hint="Supporters module · Phase 4" />
        <x-ui.stat label="Active volunteers" icon="hand-heart" hint="Volunteers module · Phase 5" />
        <x-ui.stat label="Open tasks" icon="list-checks" hint="Tasks module · Phase 5" />
        <x-ui.stat label="Sync backlog" icon="refresh-cw" hint="Offline sync · Phase 7" />
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <x-ui.card title="Module delivery" description="Derived from the navigation configuration: what is live and what is planned.">
                <ol class="relative space-y-6 before:absolute before:top-2 before:bottom-2 before:left-[0.9375rem] before:w-px before:bg-line">
                    @foreach ($phases as $phase => $modules)
                        @php($live = collect($modules)->every('available'))
                        <li class="relative flex gap-4">
                            <span @class(['relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold ring-4 ring-surface tabular',
                                          'bg-primary text-on-primary' => $live, 'border border-line-strong bg-surface text-muted' => ! $live])>{{ $phase }}</span>
                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-sm font-semibold text-fg">Phase {{ $phase }} @if ($live) <x-ui.badge tone="success" size="sm" class="ml-1.5">Live</x-ui.badge> @endif</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($modules as $module)
                                        <span @class(['inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs',
                                                      'border-primary/25 bg-primary-soft text-primary-soft-fg' => $module['available'],
                                                      'border-line text-muted' => ! $module['available']])>
                                            <x-icon :name="$module['icon']" class="size-3.5" />{{ $module['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>

            <x-ui.card title="Recent activity">
                <x-ui.empty-state compact icon="scroll-text" title="No activity yet"
                                  description="Sign-ins, approvals and record changes will appear here once the audit log is in place (Phase 3)." />
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <livewire:system.health-status-panel />

            <x-ui.card title="Shortcuts">
                <div class="grid gap-2">
                    <button type="button" x-on:click="$dispatch('open-command-palette')" class="group flex items-center gap-3 rounded-lg border border-line p-3 text-left transition-colors hover:border-line-strong hover:bg-surface-hover">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="command" /></span>
                        <span class="flex-1"><span class="block text-sm font-medium text-fg">Command palette</span><span class="block text-xs text-subtle">Jump to any page</span></span>
                        <span class="hidden gap-1 sm:flex"><x-ui.kbd>Ctrl</x-ui.kbd><x-ui.kbd>K</x-ui.kbd></span>
                    </button>
                    <a href="{{ route('design-system.sign-in') }}" class="group flex items-center gap-3 rounded-lg border border-line p-3 transition-colors hover:border-line-strong hover:bg-surface-hover">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="key-round" /></span>
                        <span class="flex-1"><span class="block text-sm font-medium text-fg">Sign-in screens</span><span class="block text-xs text-subtle">Phone + one-time code design</span></span>
                        <x-icon name="arrow-right" class="text-subtle transition-transform group-hover:translate-x-0.5" />
                    </a>
                    @if (app()->environment('local'))
                        <a href="{{ url(config('horizon.path')) }}" class="group flex items-center gap-3 rounded-lg border border-line p-3 transition-colors hover:border-line-strong hover:bg-surface-hover">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="server" /></span>
                            <span class="flex-1"><span class="block text-sm font-medium text-fg">Queue dashboard</span><span class="block text-xs text-subtle">Horizon (local environment)</span></span>
                            <x-icon name="arrow-right" class="text-subtle transition-transform group-hover:translate-x-0.5" />
                        </a>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::app>
