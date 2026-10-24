{{--
    Connection and offline-sync status. The dot shows online/offline; a count appears when
    captures are waiting on this device. The panel offers "Sync now", items needing attention
    and the low-data switch.
--}}
<div x-data="{ open: false }" x-on:keydown.escape.stop="open = false; $refs.trigger.focus()" class="relative">
    <button type="button" x-ref="trigger" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="true"
            class="relative flex h-9 items-center gap-1.5 rounded-md px-2 text-muted hover:bg-surface-sunken hover:text-fg">
        <span class="relative flex size-2.5">
            <span class="absolute inline-flex size-full rounded-full opacity-60" :class="$store.network.online ? 'bg-success' : 'animate-ping bg-danger'"></span>
            <span class="relative inline-flex size-2.5 rounded-full" :class="$store.network.online ? 'bg-success' : 'bg-danger'"></span>
        </span>
        <template x-if="$store.sync.pending > 0 || $store.sync.attention.length > 0">
            <span class="inline-flex items-center gap-1 text-xs font-semibold tabular-nums" :class="$store.sync.attention.length ? 'text-danger' : 'text-warning'">
                <x-icon name="refresh-cw" class="size-3.5" x-bind:class="$store.sync.syncing && 'animate-spin'" />
                <span x-text="$store.sync.pending + $store.sync.attention.length"></span>
            </span>
        </template>
        <span class="sr-only" x-text="($store.network.online ? @js(__('ui.online')) : @js(__('ui.offline'))) + ($store.sync.pending ? ', ' + $store.sync.pending + ' ' + @js(__('sync.waiting')) : '')"></span>
    </button>

    <div x-show="open" x-cloak x-anchor.bottom-end.offset.6="$refs.trigger" x-on:click.outside="open = false" x-transition.opacity
         class="z-50 w-[min(21rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-line bg-surface shadow-lg" role="dialog" aria-label="{{ __('sync.title') }}">
        <div class="border-b border-line px-4 py-3">
            <p class="flex items-center gap-2 text-sm font-semibold text-fg">
                <span class="size-2 rounded-full" :class="$store.network.online ? 'bg-success' : 'bg-danger'"></span>
                <span x-text="$store.network.online ? @js(__('sync.online')) : @js(__('sync.offline'))"></span>
            </p>
            <p class="mt-0.5 text-xs text-muted" x-show="$store.sync.supported">
                <span x-text="$store.sync.pending === 0 ? @js(__('sync.all_synced')) : $store.sync.pending + ' ' + @js(__('sync.waiting'))"></span>
                <template x-if="$store.sync.lastSync"><span> · {{ __('sync.last') }} <span x-text="new Date($store.sync.lastSync).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })"></span></span></template>
            </p>
            <p class="mt-0.5 text-xs text-warning" x-show="!$store.sync.supported" x-cloak>{{ __('sync.unsupported') }}</p>
            <p class="mt-1.5 text-xs text-danger" x-show="$store.sync.problem === 'auth' || $store.sync.problem === 'locked'" x-cloak>{{ __('sync.sign_in') }}</p>
            <p class="mt-1.5 text-xs text-danger" x-show="$store.sync.problem === 'server'" x-cloak>{{ __('sync.server_problem') }}</p>
        </div>

        <template x-if="$store.sync.attention.length">
            <ul class="max-h-60 divide-y divide-line overflow-y-auto scroll-thin">
                <template x-for="item in $store.sync.attention" :key="item.uuid">
                    <li class="px-4 py-3">
                        <p class="text-sm font-medium text-fg" x-text="item.label || item.list"></p>
                        <p class="text-xs text-danger" x-text="item.message"></p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 text-xs font-medium text-fg hover:border-line-strong" x-on:click="$store.sync.retry(item.uuid)">{{ __('sync.retry') }}</button>
                            <button type="button" class="rounded-md px-2.5 py-1 text-xs font-medium text-muted hover:text-danger" x-on:click="$store.sync.discard(item.uuid)">{{ __('sync.discard') }}</button>
                        </div>
                    </li>
                </template>
            </ul>
        </template>

        <div class="flex items-center justify-between gap-3 border-t border-line px-4 py-3">
            <label class="flex cursor-pointer items-center gap-2 text-[0.8125rem] text-fg">
                <input type="checkbox" class="size-4 accent-[var(--c-primary)]" :checked="$store.lowData.on" x-on:change="$store.lowData.toggle()">
                {{ __('sync.low_data') }}
            </label>
            <button type="button" x-show="$store.sync.supported" x-on:click="$store.sync.flush()" :disabled="$store.sync.syncing || !$store.network.online || $store.sync.pending === 0"
                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-on-primary disabled:opacity-50">
                <x-icon name="refresh-cw" class="size-3.5" x-bind:class="$store.sync.syncing && 'animate-spin'" /> {{ __('sync.sync_now') }}
            </button>
        </div>
        <p class="border-t border-line bg-surface-sunken/60 px-4 py-2 text-[0.6875rem] text-subtle">{{ __('sync.low_data_help') }}</p>
    </div>
</div>
