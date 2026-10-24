@php
    $sections = [
        'foundations' => 'Foundations',
        'buttons' => 'Buttons',
        'badges' => 'Badges & status',
        'forms' => 'Forms',
        'feedback' => 'Feedback',
        'overlays' => 'Overlays',
        'navigation' => 'Navigation',
        'data' => 'Data display',
        'pages' => 'Page previews',
    ];
    $scales = ['brand' => 'Brand · provisional', 'accent' => 'Accent · provisional', 'ink' => 'Ink · neutral'];
    $steps = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
    $semantic = [
        ['bg-canvas', 'canvas'], ['bg-surface', 'surface'], ['bg-surface-sunken', 'surface-sunken'], ['bg-primary', 'primary'],
        ['bg-success', 'success'], ['bg-warning', 'warning'], ['bg-danger', 'danger'], ['bg-info', 'info'], ['bg-nav', 'nav'],
    ];
@endphp
<x-layouts::app :title="__('nav.design_system')" :breadcrumbs="[__('nav.groups.administration') => null, __('nav.design_system') => null]">
    <x-ui.page-header :title="__('nav.design_system')" eyebrow="Reference"
                      description="Tokens and components shared by every surface: workspaces, PWA, public site and authentication. Change the brand once in resources/css/app.css.">
        <x-slot:actions>
            <x-ui.badge tone="accent" icon="palette">Provisional brand</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-10 xl:grid-cols-[200px_minmax(0,1fr)]">
        <nav aria-label="Sections" class="hidden xl:block">
            <ul class="sticky top-24 space-y-0.5 border-l border-line">
                @foreach ($sections as $id => $label)
                    <li><a href="#{{ $id }}" class="-ml-px block border-l border-transparent py-1.5 pl-4 text-[0.8125rem] text-muted hover:border-line-strong hover:text-fg">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 space-y-14">
            {{-- Foundations --}}
            <section id="foundations" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Foundations</h2>

                <x-ui.alert tone="warning" title="Brand assets pending approval">
                    No approved logo, palette or typography has been supplied. The scales below are a provisional direction; no campaign logo or slogan is used anywhere.
                </x-ui.alert>

                <x-ui.card title="Colour scales">
                    <div class="space-y-5">
                        @foreach ($scales as $scale => $label)
                            <div>
                                <p class="mb-2 text-[0.8125rem] font-medium text-muted">{{ $label }}</p>
                                <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-11">
                                    @foreach ($steps as $step)
                                        <div>
                                            <div class="h-10 rounded-md ring-1 ring-line ring-inset" style="background: var(--color-{{ $scale }}-{{ $step }})"></div>
                                            <p class="mt-1 text-[0.6875rem] text-subtle tabular">{{ $step }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <x-ui.card title="Semantic roles" description="Components use roles, never raw scale values. Roles switch with the theme.">
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ($semantic as [$class, $name])
                                <div class="overflow-hidden rounded-lg border border-line">
                                    <div class="h-12 {{ $class }}"></div>
                                    <p class="px-2 py-1.5 font-mono text-[0.6875rem] text-muted">{{ $name }}</p>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Typography" description="Inter for interface and data; Fraunces for public display headings.">
                        <div class="space-y-4">
                            <p class="font-display text-4xl leading-tight font-medium tracking-tight">Display heading</p>
                            <p class="text-2xl font-semibold tracking-tight">Page title · 24/28</p>
                            <p class="text-[0.9375rem] font-semibold">Section title · 15</p>
                            <p class="text-sm text-muted">Body text · 14/24. Comfortable for dense operational screens without feeling cramped.</p>
                            <p class="text-xs text-subtle">Caption · 12</p>
                            <p class="text-2xl font-semibold tabular">1,234,567.89 <span class="text-sm font-normal text-subtle">tabular numerals</span></p>
                        </div>
                    </x-ui.card>
                </div>

                <x-ui.card title="Radius & elevation">
                    <div class="flex flex-wrap gap-4">
                        @foreach (['shadow-xs' => 'rounded-md', 'shadow-sm' => 'rounded-lg', 'shadow-md' => 'rounded-xl', 'shadow-lg' => 'rounded-2xl'] as $shadow => $radius)
                            <div class="flex h-20 w-32 items-end border border-line bg-surface-raised p-3 {{ $shadow }} {{ $radius }}">
                                <p class="font-mono text-[0.6875rem] text-muted">{{ $shadow }}<br>{{ $radius }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </section>

            {{-- Buttons --}}
            <section id="buttons" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Buttons</h2>
                <x-ui.card>
                    <div class="space-y-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.button icon="plus">Primary</x-ui.button>
                            <x-ui.button variant="secondary" icon="filter">Secondary</x-ui.button>
                            <x-ui.button variant="soft">Soft</x-ui.button>
                            <x-ui.button variant="ghost">Ghost</x-ui.button>
                            <x-ui.button variant="danger" icon="trash-2">Danger</x-ui.button>
                            <x-ui.button disabled>Disabled</x-ui.button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.button size="sm">Small</x-ui.button>
                            <x-ui.button size="md">Medium</x-ui.button>
                            <x-ui.button size="lg" icon-trailing="arrow-right">Large</x-ui.button>
                            <x-ui.tooltip text="Icon-only buttons need a label and a tooltip">
                                <x-ui.button variant="secondary" square icon="settings"><span class="sr-only">Settings</span></x-ui.button>
                            </x-ui.tooltip>
                            <x-ui.button variant="secondary" x-data="{ busy: false }" x-on:click="busy = true; setTimeout(() => busy = false, 1500)" x-bind:disabled="busy">
                                <x-ui.spinner class="size-4" x-show="busy" x-cloak />
                                <span x-text="busy ? 'Working…' : 'Click for busy state'"></span>
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </section>

            {{-- Badges --}}
            <section id="badges" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Badges & status</h2>
                <x-ui.card>
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            @foreach (['neutral', 'brand', 'success', 'warning', 'danger', 'info', 'accent'] as $tone)
                                <x-ui.badge :tone="$tone" dot>{{ ucfirst($tone) }}</x-ui.badge>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tone="info" icon="file-check">Submitted</x-ui.badge>
                            <x-ui.badge tone="success" icon="shield-check">Verified</x-ui.badge>
                            <x-ui.badge tone="brand" icon="stamp">Official source</x-ui.badge>
                            <x-ui.sample-badge />
                        </div>
                        <p class="text-[0.8125rem] text-muted">Election-day data always carries one of <em>Submitted</em>, <em>Verified</em> or <em>Official source</em>. Synthetic data always carries <strong>SAMPLE</strong>.</p>
                    </div>
                </x-ui.card>
            </section>

            {{-- Forms --}}
            <section id="forms" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Forms</h2>
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                    <x-ui.card title="Validated form" description="Server-side rules, inline errors on blur, accessible descriptions.">
                        <livewire:design-system.sample-form />
                    </x-ui.card>
                    <div class="space-y-6">
                        <x-ui.card title="Controls">
                            <div class="space-y-5">
                                <x-ui.input name="ds-search" label="With icon" icon="search" placeholder="Search…" />
                                <x-ui.input name="ds-error" label="Error state" value="080" error="Enter a valid Nigerian mobile number, for example 0803 000 0000." />
                                <x-ui.input name="ds-disabled" label="Disabled" value="Read-only value" disabled />
                                <div x-data="{ on: true }" class="space-y-4">
                                    <x-ui.switch label="Low-data mode" description="Defer images and non-essential requests." x-model="on" />
                                </div>
                            </div>
                        </x-ui.card>
                        <x-ui.card title="One-time code">
                            <x-ui.otp-input name="ds-code" />
                        </x-ui.card>
                    </div>
                </div>
            </section>

            {{-- Feedback --}}
            <section id="feedback" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Feedback</h2>
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-ui.card title="Alerts">
                        <div class="space-y-3">
                            <x-ui.alert tone="info" title="Information">Synchronisation runs when you are back online.</x-ui.alert>
                            <x-ui.alert tone="success" title="Saved">Your changes were saved.</x-ui.alert>
                            <x-ui.alert tone="warning" title="Needs attention">3 records could not be synchronised.</x-ui.alert>
                            <x-ui.alert tone="danger" title="Action failed" dismissible>The provider rejected the request. Try again later.</x-ui.alert>
                        </div>
                    </x-ui.card>
                    <div class="space-y-6">
                        <x-ui.card title="Toasts">
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button variant="secondary" size="sm" x-on:click="$store.toasts.push({ type: 'success', title: 'Task completed', message: 'Sample notification.' })">Success</x-ui.button>
                                <x-ui.button variant="secondary" size="sm" x-on:click="$store.toasts.push({ type: 'warning', title: 'Quiet hours', message: 'Messages will send after 8:00.' })">Warning</x-ui.button>
                                <x-ui.button variant="secondary" size="sm" x-on:click="$store.toasts.push({ type: 'danger', title: 'Upload failed', message: 'The file exceeded 10 MB.' })">Error</x-ui.button>
                                <x-ui.button variant="secondary" size="sm" x-on:click="$store.toasts.push({ type: 'info', title: 'Sync started' })">Info</x-ui.button>
                            </div>
                        </x-ui.card>
                        <x-ui.card title="Loading & progress">
                            <div class="space-y-5">
                                <div class="flex gap-4"><x-ui.skeleton class="size-10 rounded-full" /><x-ui.skeleton :lines="3" class="flex-1" /></div>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-[0.8125rem]"><span class="text-muted">Import progress</span><span class="font-medium tabular">64%</span></div>
                                    <x-ui.progress :value="64" label="Import progress" />
                                </div>
                            </div>
                        </x-ui.card>
                    </div>
                </div>
                <x-ui.card :padding="false">
                    <x-ui.empty-state icon="map-pinned" title="No walk lists assigned" description="Walk lists appear here once your coordinator assigns polling units to you.">
                        <x-ui.button variant="secondary" size="sm" icon="refresh-cw" x-on:click="$store.toasts.push({ type: 'info', title: 'Checked for new assignments', message: 'Sample — nothing to fetch yet.' })">Check again</x-ui.button>
                    </x-ui.empty-state>
                </x-ui.card>
            </section>

            {{-- Overlays --}}
            <section id="overlays" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Overlays</h2>
                <x-ui.card description="Focus is trapped while open, Esc closes, focus returns to the trigger.">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('open-modal', 'ds-modal')">Open dialog</x-ui.button>
                        <x-ui.button variant="danger" icon="trash-2" x-on:click="$dispatch('open-modal', 'ds-confirm')">Delete…</x-ui.button>
                        <x-ui.button variant="secondary" icon="panel-left-open" x-on:click="$dispatch('open-modal', 'ds-slide')">Open slide-over</x-ui.button>
                        <x-ui.dropdown>
                            <x-slot:trigger class="h-10 border border-line-strong bg-surface px-4 text-sm font-medium text-fg shadow-xs hover:bg-surface-hover">
                                Menu <x-icon name="chevron-down" class="text-subtle" />
                            </x-slot:trigger>
                            <x-ui.dropdown-item icon="eye" x-on:click="open = false; $store.toasts.push({ type: 'info', title: 'View selected' })">View</x-ui.dropdown-item>
                            <x-ui.dropdown-item icon="pencil" x-on:click="open = false; $store.toasts.push({ type: 'info', title: 'Edit selected' })">Edit</x-ui.dropdown-item>
                            <x-ui.dropdown-item icon="copy" x-on:click="open = false; $store.toasts.push({ type: 'info', title: 'Duplicate selected' })">Duplicate</x-ui.dropdown-item>
                            <div class="my-1 border-t border-line"></div>
                            <x-ui.dropdown-item icon="trash-2" danger x-on:click="open = false; $dispatch('open-modal', 'ds-confirm')">Delete…</x-ui.dropdown-item>
                        </x-ui.dropdown>
                        <x-ui.tooltip text="Tooltips explain complex controls">
                            <x-ui.button variant="ghost" square icon="circle-help"><span class="sr-only">Help</span></x-ui.button>
                        </x-ui.tooltip>
                    </div>
                </x-ui.card>

                <x-ui.modal name="ds-modal" title="Assign polling unit" description="Choose the agent responsible for this polling unit.">
                    <div class="space-y-4">
                        <x-ui.select name="ds-agent" label="Agent" placeholder="Select an agent" :options="['a' => 'Sample Agent 01', 'b' => 'Sample Agent 02']" />
                        <x-ui.textarea name="ds-note" label="Note" optional rows="3" />
                    </div>
                    <x-slot:footer>
                        <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                        <x-ui.button x-on:click="open = false; $store.toasts.push({ type: 'success', title: 'Assigned', message: 'Sample — nothing saved.' })">Assign</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>

                <x-ui.confirm-dialog name="ds-confirm" title="Delete this draft?" description="This removes the draft and its version history. This cannot be undone.">
                    <x-slot:confirm>
                        <x-ui.button variant="danger" x-on:click="open = false; $store.toasts.push({ type: 'success', title: 'Draft deleted', message: 'Sample — nothing was deleted.' })">Delete draft</x-ui.button>
                    </x-slot:confirm>
                </x-ui.confirm-dialog>

                <x-ui.slide-over name="ds-slide" title="Polling unit details" description="Contextual details without leaving the list.">
                    <x-ui.skeleton :lines="6" />
                    <x-slot:footer>
                        <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.close') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.slide-over>
            </section>

            {{-- Navigation --}}
            <section id="navigation" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Navigation</h2>
                <x-ui.card>
                    <div class="space-y-6">
                        <x-ui.breadcrumbs :items="['Delta State' => '#navigation', 'Delta North' => '#navigation', 'Sample LGA' => '#navigation', 'Sample ward' => null]" />
                        <x-ui.tabs :tabs="['overview' => 'Overview', 'volunteers' => 'Volunteers', 'history' => 'History']">
                            <div x-show="tab === 'overview'" x-bind="panel('overview')" class="text-sm text-muted">Tabs switch views of the same record. Use ← → to move between tabs.</div>
                            <div x-show="tab === 'volunteers'" x-bind="panel('volunteers')" x-cloak class="text-sm text-muted">Volunteer assignments for this ward (Phase 5).</div>
                            <div x-show="tab === 'history'" x-bind="panel('history')" x-cloak class="text-sm text-muted">Audit history for this record (Phase 3).</div>
                        </x-ui.tabs>
                        <p class="text-[0.8125rem] text-muted">Press <x-ui.kbd>Ctrl</x-ui.kbd> <x-ui.kbd>K</x-ui.kbd> anywhere in the workspace to open the command palette.</p>
                    </div>
                </x-ui.card>
            </section>

            {{-- Data --}}
            <section id="data" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Data display</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.stat label="Supporters" value="12,480" delta="+4.2%" icon="users" sample :points="[3, 5, 4, 6, 7, 6, 9, 10, 12]" />
                    <x-ui.stat label="Contacts this week" value="3,912" delta="+12%" icon="phone" sample :points="[8, 7, 9, 6, 10, 12, 11, 14]" />
                    <x-ui.stat label="Overdue tasks" value="27" delta="−3" delta-tone="success" icon="list-checks" sample />
                    <x-ui.stat label="No data yet" icon="clock" hint="Tiles without data show a dash, never zero." />
                </div>
                <livewire:design-system.sample-records-table />
            </section>

            {{-- Pages --}}
            <section id="pages" class="scroll-mt-24 space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Page previews</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['design-system.shell', [], 'layout-dashboard', 'Workspace dashboard', 'Application shell with live system status.'],
                        ['design-system.sign-in', [], 'key-round', 'Sign in', 'Phone number entry (functional in Phase 3).'],
                        ['design-system.verify', [], 'message-square', 'Verify code', 'One-time code entry with resend timer.'],
                        ['home', [], 'globe', 'Public holding page', 'Public layout (full website in Phase 10).'],
                        ['design-system.error', ['status' => '404'], 'circle-x', 'Error pages', '403, 404, 419, 429, 500 and 503.'],
                    ] as [$route, $params, $icon, $label, $text])
                        <a href="{{ route($route, $params) }}" class="group flex gap-4 rounded-xl border border-line bg-surface p-4 shadow-xs transition-[border-color,box-shadow] hover:border-line-strong hover:shadow-sm">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-5" /></span>
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-fg">{{ $label }} <x-icon name="arrow-up-right" class="size-3.5 text-subtle transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" /></span>
                                <span class="mt-0.5 block text-[0.8125rem] text-muted">{{ $text }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
