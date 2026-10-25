@php($tz = config('campaign.display_timezone'))
<x-layouts::public :title="$project->title" :description="\Illuminate\Support\Str::limit(strip_tags($project->description), 155)" :image="$project->cover?->url(1600)" type="article">
    <x-public.band :title="$project->title" :eyebrow="__('projects.category_'.$project->category)">
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($project->lga) <a href="{{ route('public.lgas.show', $project->lga->slug) }}"><x-ui.badge tone="info" icon="map-pinned">{{ $project->lga->name }}@if ($project->ward) · {{ $project->ward->name }}@endif</x-ui.badge></a> @endif
            <x-ui.badge :tone="$project->verification->tone()" icon="shield-check">{{ $project->verification->label() }}</x-ui.badge>
            @if ($project->is_sample) <x-ui.sample-badge /> @endif
        </div>
    </x-public.band>
    <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div>
            @if ($project->cover) <img src="{{ $project->cover->url(960) }}" srcset="{{ $project->cover->srcset() }}" sizes="(min-width: 1024px) 720px, 100vw" alt="{{ $project->cover->alt() }}" class="mb-8 w-full rounded-xl" decoding="async"> @endif
            <x-public.markdown :text="$project->description" />
        </div>
        <aside class="space-y-4">
            <dl class="space-y-3 rounded-xl border border-line bg-surface p-5 text-sm">
                @if ($project->location) <div><dt class="text-muted">{{ __('public.location') }}</dt><dd class="mt-0.5 text-fg">{{ $project->location }}</dd></div> @endif
                @if ($project->start_date) <div><dt class="text-muted">{{ __('public.dates') }}</dt><dd class="mt-0.5 text-fg">{{ $project->start_date->format('M Y') }}@if ($project->end_date) – {{ $project->end_date->format('M Y') }}@endif</dd></div> @endif
                <div><dt class="text-muted">{{ __('public.source') }}</dt><dd class="mt-0.5 text-fg">{{ $project->source_note }}</dd></div>
                <div><dt class="text-muted">{{ __('public.published') }}</dt><dd class="mt-0.5 text-fg">{{ $project->published_at?->timezone($tz)->format('j M Y') }}</dd></div>
            </dl>
            <p class="text-xs leading-5 text-subtle">{{ __('public.project_disclaimer') }}</p>
            <a href="{{ route('public.contact') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">{{ __('public.report_correction') }} <x-icon name="arrow-right" class="size-4" /></a>
        </aside>
    </div>
</x-layouts::public>
