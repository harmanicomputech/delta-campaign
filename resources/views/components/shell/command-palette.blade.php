@props(['items'])
<script type="application/json" id="command-palette-items" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($items)</script>
<div x-data="commandPalette('command-palette-items')" x-on:open-command-palette.window="show()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="{{ __('ui.search') }}"
             x-on:keydown.escape.prevent.stop="close()">
            <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink-950/50 backdrop-blur-[2px]" x-on:click="close()" aria-hidden="true"></div>
            <div x-show="open" x-trap.inert.noscroll="open" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="scale-[0.98] opacity-0" x-transition:enter-end="scale-100 opacity-100"
                 class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-line bg-surface-raised shadow-lg">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <x-icon name="search" class="size-5 text-subtle" />
                    <input x-ref="input" x-model="query" x-on:input="active = 0" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="select()"
                           type="search" placeholder="{{ __('ui.search_placeholder') }}" aria-label="{{ __('ui.search') }}" autocomplete="off"
                           role="combobox" aria-expanded="true" aria-controls="command-palette-list" :aria-activedescendant="'cp-item-' + active"
                           class="h-14 w-full bg-transparent text-[0.9375rem] text-fg outline-none placeholder:text-subtle focus-visible:outline-none">
                    <x-ui.kbd>Esc</x-ui.kbd>
                </div>
                <ul x-ref="list" id="command-palette-list" role="listbox" class="scroll-thin max-h-80 overflow-y-auto p-2">
                    <template x-for="(item, index) in results" :key="item.url">
                        <li :id="'cp-item-' + index" :data-index="index" role="option" :aria-selected="index === active"
                            x-on:click="select(index)" x-on:mousemove="active = index"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm"
                            :class="index === active ? 'bg-primary-soft text-primary-soft-fg' : 'text-fg'">
                            <span x-text="item.label" class="font-medium"></span>
                            <span x-text="item.group" class="text-xs text-subtle"></span>
                        </li>
                    </template>
                    <li x-show="results.length === 0" class="px-3 py-8 text-center text-sm text-muted">{{ __('ui.no_results') }}</li>
                </ul>
                <div class="border-t border-line bg-surface-sunken/50 px-4 py-2.5 text-xs text-subtle">{{ __('ui.command_hint') }}</div>
            </div>
        </div>
    </template>
</div>
