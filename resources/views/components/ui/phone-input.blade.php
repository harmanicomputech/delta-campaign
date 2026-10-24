{{-- Nigerian mobile number. Normalisation to E.164 happens server-side. --}}
@props(['name' => 'phone', 'label' => null, 'hint' => null, 'required' => true])
<x-ui.input
    :name="$name"
    :label="$label ?? __('auth.phone')"
    :hint="$hint ?? __('auth.phone_hint')"
    prefix="+234"
    type="tel"
    inputmode="tel"
    autocomplete="tel-national"
    maxlength="14"
    placeholder="803 000 0000"
    :required="$required"
    {{ $attributes }}
/>
