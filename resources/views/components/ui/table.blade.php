{{--
    Responsive data table. Slots: head (<tr> of <x-ui.th>), default (body rows), footer.
    Scrolls horizontally inside its container on small screens; the header stays sticky.
--}}
@props(['dense' => false, 'caption' => null])
<div {{ $attributes->class('scroll-thin relative overflow-x-auto') }}>
    <table @class(['w-full border-separate border-spacing-0 text-left text-sm', 'table-dense' => $dense])>
        @if ($caption) <caption class="sr-only">{{ $caption }}</caption> @endif
        <thead class="sticky top-0 z-10 bg-surface-sunken/80 backdrop-blur-sm">
            {{ $head }}
        </thead>
        <tbody class="[&>tr]:transition-colors [&>tr:hover]:bg-surface-hover [&>tr>td]:border-b [&>tr>td]:border-line [&>tr:last-child>td]:border-b-0">
            {{ $slot }}
        </tbody>
    </table>
    @isset($footer) <div class="border-t border-line">{{ $footer }}</div> @endisset
</div>
