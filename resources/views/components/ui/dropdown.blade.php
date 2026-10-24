{{-- Menu button. Slot "trigger" is the button content; default slot holds <x-ui.dropdown-item>s. --}}
@props(['align' => 'bottom-end', 'width' => 'w-56', 'label' => null])
<div x-data="{ open: false }" x-on:keydown.escape.stop="open = false; $refs.trigger.focus()" class="relative" {{ $attributes }}>
    <button type="button" x-ref="trigger" x-on:click="open = !open" aria-haspopup="menu" :aria-expanded="open"
            @if ($label) aria-label="{{ $label }}" @endif
            {{ $trigger->attributes->class('inline-flex items-center gap-2 rounded-md') }}>
        {{ $trigger }}
    </button>
    <div x-show="open" x-cloak x-anchor.{{ $align }}.offset.6="$refs.trigger"
         x-on:click.outside="open = false"
         x-on:keydown.down.prevent="$focus.wrap().next()"
         x-on:keydown.up.prevent="$focus.wrap().previous()"
         x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transition duration-75 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-init="$watch('open', value => value && $nextTick(() => $focus.within($el).first()))"
         role="menu"
         class="z-40 {{ $width }} origin-top-right rounded-lg border border-line bg-surface-raised p-1 shadow-lg">
        {{ $slot }}
    </div>
</div>
