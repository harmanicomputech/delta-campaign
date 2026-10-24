{{--
    tabs: [key => label]. Panels in the slot use x-show="tab === 'key'" and
    x-bind="panel('key')" for the ARIA wiring.
--}}
@props(['tabs' => [], 'default' => null, 'id' => 'tabs-'.\Illuminate\Support\Str::random(4)])
<div x-data="{
        tab: @js($default ?? array_key_first($tabs)),
        keys: @js(array_keys($tabs)),
        go(step) { const i = (this.keys.indexOf(this.tab) + step + this.keys.length) % this.keys.length; this.tab = this.keys[i]; this.$nextTick(() => document.getElementById('{{ $id }}-' + this.tab).focus()) },
        panel(key) { return { role: 'tabpanel', id: '{{ $id }}-panel-' + key, 'aria-labelledby': '{{ $id }}-' + key, tabindex: 0 } },
     }" {{ $attributes }}>
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-line" x-on:keydown.right.prevent="go(1)" x-on:keydown.left.prevent="go(-1)">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="{{ $id }}-{{ $key }}" aria-controls="{{ $id }}-panel-{{ $key }}"
                    x-on:click="tab = @js($key)" :aria-selected="tab === @js($key)" :tabindex="tab === @js($key) ? 0 : -1"
                    class="relative -mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="tab === @js($key) ? 'border-primary text-fg' : 'border-transparent text-subtle hover:text-fg'">{{ $label }}</button>
        @endforeach
    </div>
    <div class="pt-5">{{ $slot }}</div>
</div>
