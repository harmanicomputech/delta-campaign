{{--
    Segmented one-time-code entry. Digits are mirrored into a single hidden input named $name,
    so the form posts one value. Supports paste, backspace navigation and SMS autofill.
--}}
@props(['name' => 'code', 'length' => 6, 'label' => null, 'error' => null])
@php($message = $error ?? $errors->first($name))
<x-ui.field :label="$label ?? __('auth.code')" for="otp-0" :error="$message" :class="$attributes->get('class')">
    <div
        x-data="{
            digits: Array({{ (int) $length }}).fill(''),
            get value() { return this.digits.join('') },
            focus(i) { this.$refs['d' + Math.max(0, Math.min({{ (int) $length - 1 }}, i))]?.focus() },
            input(i, event) {
                const clean = event.target.value.replace(/\D/g, '');
                if (clean.length > 1) { this.fill(clean, i); return }
                this.digits[i] = clean;
                if (clean) this.focus(i + 1);
            },
            fill(text, start = 0) {
                text.replace(/\D/g, '').slice(0, {{ (int) $length }} - start).split('').forEach((d, k) => this.digits[start + k] = d);
                this.focus(start + text.length);
            },
            back(i) { if (!this.digits[i] && i > 0) { this.digits[i - 1] = ''; this.focus(i - 1) } },
        }"
        class="flex gap-2 sm:gap-2.5"
        role="group"
        aria-label="{{ $label ?? __('auth.code') }}"
    >
        <input type="hidden" name="{{ $name }}" :value="value" disabled x-bind:disabled="false" {{ $attributes->whereStartsWith('x-model') }}>
        <noscript>
            <input type="text" name="{{ $name }}" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $length }}" aria-label="{{ $label ?? __('auth.code') }}" class="form-control h-12 text-center text-lg tracking-[0.5em]">
        </noscript>
        @for ($i = 0; $i < $length; $i++)
            <input
                id="otp-{{ $i }}"
                x-ref="d{{ $i }}"
                x-model="digits[{{ $i }}]"
                x-on:input="input({{ $i }}, $event)"
                x-on:keydown.backspace="back({{ $i }})"
                x-on:keydown.left.prevent="focus({{ $i - 1 }})"
                x-on:keydown.right.prevent="focus({{ $i + 1 }})"
                x-on:paste.prevent="fill($event.clipboardData.getData('text'), {{ $i }})"
                x-on:focus="$event.target.select()"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="{{ $i === 0 ? $length : 1 }}"
                @if ($i === 0) autocomplete="one-time-code" @else autocomplete="off" @endif
                aria-label="{{ __('auth.code') }} {{ $i + 1 }}"
                @if ($message) aria-invalid="true" @endif
                class="form-control h-12 w-11 px-0 text-center text-lg font-semibold tabular sm:h-14 sm:w-12"
                x-cloak
            >
        @endfor
    </div>
</x-ui.field>
