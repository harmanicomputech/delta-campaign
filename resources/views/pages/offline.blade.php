{{--
    Offline shell, cached by the service worker and shown when a page cannot be reached.
    It contains no personal data itself: walk lists come from the device's encrypted store.
--}}
@php
    $outcomes = collect(\App\Livewire\Workspace\Canvassing\WalkListShow::QUICK_OUTCOMES)->mapWithKeys(fn ($o) => [$o => \App\Enums\ContactOutcome::from($o)->label()]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <x-shell.head :title="__('sync.offline_title')" />
</head>
<body x-data="{ openList: null, openEntry: null }" class="min-h-dvh bg-canvas">
    <header class="sticky top-0 z-20 border-b border-line bg-canvas/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-3xl items-center gap-3 px-4">
            <x-shell.brand compact />
            <span class="ml-auto inline-flex items-center gap-2 text-xs font-medium" :class="$store.network.online ? 'text-success' : 'text-danger'">
                <span class="size-2 rounded-full" :class="$store.network.online ? 'bg-success' : 'bg-danger'"></span>
                <span x-text="$store.network.online ? @js(__('sync.online')) : @js(__('sync.offline'))"></span>
            </span>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-3xl space-y-5 px-4 py-6">
        <section class="rounded-xl border border-line bg-surface p-5 shadow-xs">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning"><x-icon name="cloud-off" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-xl font-semibold tracking-tight text-fg">{{ __('sync.offline_title') }}</h1>
                    <p class="mt-1 text-sm text-muted">{{ __('sync.offline_body') }}</p>
                    <p class="mt-2 text-sm font-medium" :class="$store.sync.pending ? 'text-warning' : 'text-success'"
                       x-text="$store.sync.pending ? $store.sync.pending + ' ' + @js(__('sync.waiting')) : @js(__('sync.all_synced'))"></p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" x-on:click="window.location.reload()" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary">
                    <x-icon name="refresh-cw" class="size-4" /> {{ __('sync.try_again') }}
                </button>
                <button type="button" x-show="$store.network.online && $store.sync.pending" x-cloak x-on:click="$store.sync.flush()" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line px-4 text-sm font-medium text-fg">
                    {{ __('sync.sync_now') }}
                </button>
            </div>
        </section>

        <section x-show="$store.sync.supported" class="rounded-xl border border-line bg-surface shadow-xs">
            <h2 class="border-b border-line px-5 py-4 text-[0.9375rem] font-semibold text-fg">{{ __('sync.cached_lists') }}</h2>
            <template x-if="$store.sync.lists.length === 0">
                <p class="px-5 py-8 text-center text-sm text-muted">{{ __('sync.no_cached_lists') }}</p>
            </template>
            <ul class="divide-y divide-line">
                <template x-for="list in $store.sync.lists" :key="list.uuid">
                    <li>
                        <button type="button" class="flex w-full items-center gap-3 px-5 py-3.5 text-left" x-on:click="openList = openList === list.uuid ? null : list.uuid" :aria-expanded="openList === list.uuid">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-fg" x-text="list.name"></span>
                                <span class="block text-xs text-muted" x-text="(list.ward || '') + ' · ' + list.entries.filter(e => e.status !== 'pending').length + '/' + list.entries.length"></span>
                            </span>
                            <x-icon name="chevron-down" class="size-4 text-subtle transition-transform" x-bind:class="openList === list.uuid && 'rotate-180'" />
                        </button>
                        <ol x-show="openList === list.uuid" x-collapse class="divide-y divide-line border-t border-line bg-surface-sunken/40">
                            <template x-for="entry in list.entries" :key="entry.id">
                                <li class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                              :class="entry.status === 'pending' ? 'bg-surface text-muted' : 'bg-success-soft text-success'" x-text="entry.status === 'pending' ? entry.position : '✓'"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-fg" x-text="entry.label"></span>
                                            <span class="block font-mono text-xs text-subtle" x-text="entry.reference || ''"></span>
                                        </span>
                                        <button type="button" class="rounded-md border border-line px-2.5 py-1.5 text-xs font-medium text-muted" x-on:click="openEntry = openEntry === entry.id ? null : entry.id">{{ __('canvassing.record') }}</button>
                                    </div>
                                    <div x-show="openEntry === entry.id" x-cloak class="mt-3 grid grid-cols-2 gap-2 pl-10">
                                        @foreach ($outcomes as $value => $label)
                                            <button type="button" class="flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-fg"
                                                    x-on:click="$store.sync.enqueue({ entryId: entry.id, outcome: '{{ $value }}', label: entry.label, listUuid: list.uuid, listName: list.name }).then(() => { openEntry = null; $store.toasts.push({ type: 'info', title: @js(__('sync.saved_on_device')) }) })">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </li>
                </template>
            </ul>
        </section>
        <p class="text-center text-xs text-subtle">{{ __('canvassing.no_opinions') }}</p>
    </main>
    <x-shell.toasts />
    @livewireScriptConfig
</body>
</html>
