@props(['icon' => 'inbox', 'title', 'description' => null, 'compact' => false])
<div {{ $attributes->class(['flex flex-col items-center text-center', 'px-6 py-14' => ! $compact, 'px-4 py-8' => $compact]) }}>
    <div class="relative mb-4">
        <div class="absolute inset-0 -m-3 rounded-full bg-primary-soft/60 blur-md" aria-hidden="true"></div>
        <div class="relative flex size-12 items-center justify-center rounded-xl border border-line bg-surface text-primary shadow-sm">
            <x-icon :name="$icon" class="size-5" />
        </div>
    </div>
    <h3 class="text-[0.9375rem] font-semibold text-fg">{{ $title }}</h3>
    @if ($description) <p class="mt-1 max-w-sm text-[0.8125rem] text-muted">{{ $description }}</p> @endif
    @if ($slot->isNotEmpty()) <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div> @endif
</div>
