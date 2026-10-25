{{-- Shown where approved content has not been published yet. Never filled with invented text. --}}
@props(['title' => null])
<div {{ $attributes->class('rounded-xl border border-dashed border-line-strong bg-surface p-6 text-center') }}>
    <x-icon name="file-text" class="mx-auto size-6 text-subtle" />
    <p class="mt-3 font-medium text-fg">{{ $title ?? __('public.not_published_title') }}</p>
    <p class="mt-1 text-sm text-muted">{{ $slot->isEmpty() ? __('public.not_published_body') : $slot }}</p>
</div>
