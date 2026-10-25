{{-- Renders approved Markdown safely: raw HTML is stripped and unsafe links are removed. --}}
@props(['text' => ''])
<div {{ $attributes->class('rich-text') }}>{!! str((string) $text)->markdown(['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
