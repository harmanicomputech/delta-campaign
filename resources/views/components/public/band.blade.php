{{-- Page heading band for inner public pages. --}}
@props(['title', 'eyebrow' => null, 'description' => null])
<section class="border-b border-line bg-surface">
    <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
        @if ($eyebrow) <p class="text-xs font-semibold tracking-[0.14em] text-primary uppercase">{{ $eyebrow }}</p> @endif
        <h1 class="mt-2 max-w-3xl font-display text-3xl leading-tight font-semibold tracking-tight text-fg sm:text-[2.5rem]">{{ $title }}</h1>
        @if ($description) <p class="mt-3 max-w-2xl text-base leading-7 text-muted">{{ $description }}</p> @endif
        {{ $slot }}
    </div>
</section>
