@props(['title', 'description' => null, 'eyebrow' => null])
<header {{ $attributes->class('mb-6 flex flex-col gap-4 sm:mb-8 lg:flex-row lg:items-end lg:justify-between') }}>
    <div class="min-w-0">
        @isset($breadcrumbs) <div class="mb-3">{{ $breadcrumbs }}</div> @endisset
        @if ($eyebrow) <p class="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-primary">{{ $eyebrow }}</p> @endif
        <h1 class="text-2xl font-semibold tracking-tight text-fg sm:text-[1.75rem] sm:leading-9">{{ $title }}</h1>
        @if ($description) <p class="mt-1.5 max-w-2xl text-[0.9375rem] text-muted">{{ $description }}</p> @endif
    </div>
    @isset($actions) <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div> @endisset
</header>
