{{--
    Confirmation step for sensitive or destructive actions.
    Put the confirming control (form submit or wire:click button) in the "confirm" slot.
--}}
@props(['name', 'title', 'description' => null, 'tone' => 'danger'])
<x-ui.modal :name="$name" :title="$title" :description="$description" max-width="md" :icon="$tone === 'danger' ? 'triangle-alert' : 'circle-help'" :tone="$tone">
    {{ $slot }}
    <x-slot:footer>
        <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
        {{ $confirm }}
    </x-slot:footer>
</x-ui.modal>
