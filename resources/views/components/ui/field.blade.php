{{--
    Label + control + hint + error wrapper. Controls (<x-ui.input> etc.) use it internally;
    use it directly to wrap custom controls.
--}}
@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false, 'optional' => false])
<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <div class="flex items-baseline justify-between gap-2">
            <label @if ($for) for="{{ $for }}" @endif class="text-[0.8125rem] font-medium text-fg">
                {{ $label }}
                @if ($required) <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">({{ __('ui.required') }})</span> @endif
            </label>
            @if ($optional) <span class="text-xs text-subtle">{{ __('ui.optional') }}</span> @endif
        </div>
    @endif
    {{ $slot }}
    @if ($error)
        <p id="{{ $for }}-error" class="flex items-start gap-1.5 text-[0.8125rem] text-danger">
            <x-icon name="circle-alert" class="mt-0.5 size-3.5" />{{ $error }}
        </p>
    @elseif ($hint)
        <p id="{{ $for }}-hint" class="text-[0.8125rem] text-subtle">{{ $hint }}</p>
    @endif
</div>
