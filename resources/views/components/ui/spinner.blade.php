@props(['label' => null])
<x-icon name="loader-circle" {{ $attributes->class(['animate-spin text-subtle']) }} :label="$label ?? __('ui.loading')" />
