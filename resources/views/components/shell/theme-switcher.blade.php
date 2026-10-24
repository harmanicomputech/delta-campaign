<x-ui.dropdown width="w-44" :label="__('ui.theme')">
    <x-slot:trigger class="size-9 justify-center text-muted hover:bg-surface-sunken hover:text-fg">
        <x-icon name="sun" class="size-[1.125rem]" x-show="$store.theme.preference === 'light'" />
        <x-icon name="moon" class="size-[1.125rem]" x-show="$store.theme.preference === 'dark'" x-cloak />
        <x-icon name="monitor" class="size-[1.125rem]" x-show="$store.theme.preference === 'system'" x-cloak />
    </x-slot:trigger>
    @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $theme => $icon)
        <button type="button" role="menuitemradio" x-on:click="$store.theme.set('{{ $theme }}'); open = false"
                :aria-checked="$store.theme.preference === '{{ $theme }}'"
                class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm text-fg outline-none hover:bg-surface-sunken focus:bg-surface-sunken">
            <x-icon :name="$icon" class="size-4 text-subtle" />
            <span class="flex-1">{{ __('ui.theme_'.$theme) }}</span>
            <x-icon name="check" class="size-4 text-primary" x-show="$store.theme.preference === '{{ $theme }}'" />
        </button>
    @endforeach
</x-ui.dropdown>
