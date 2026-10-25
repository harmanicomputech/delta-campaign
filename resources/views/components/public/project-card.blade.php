@props(['project'])
<a href="{{ route('public.projects.show', $project->slug) }}" {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs transition hover:-translate-y-0.5 hover:shadow-md') }}>
    @if ($project->cover)
        <img src="{{ $project->cover->url(480) }}" srcset="{{ $project->cover->srcset() }}" sizes="(min-width: 1024px) 360px, 100vw" alt="{{ $project->cover->alt() }}" loading="lazy" decoding="async" class="aspect-[16/9] w-full object-cover">
    @endif
    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-1.5">
            <x-ui.badge size="sm">{{ __('projects.category_'.$project->category) }}</x-ui.badge>
            @if ($project->lga) <x-ui.badge size="sm" tone="info">{{ $project->lga->name }}</x-ui.badge> @endif
            @if ($project->is_sample) <x-ui.sample-badge /> @endif
        </div>
        <h3 class="mt-3 font-display text-lg leading-snug font-semibold text-fg group-hover:text-primary">{{ $project->title }}</h3>
        <p class="mt-2 line-clamp-3 text-sm text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) str($project->description)->markdown(['html_input' => 'strip'])), 160) }}</p>
    </div>
</a>
