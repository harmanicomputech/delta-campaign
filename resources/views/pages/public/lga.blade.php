<x-layouts::public :title="$lga->name.' LGA'" :description="__('public.lga_intro', ['lga' => $lga->name, 'district' => $lga->senatorialDistrict->name])">
    <x-public.band :title="$lga->name" :eyebrow="$lga->senatorialDistrict->name" :description="__('public.lga_intro', ['lga' => $lga->name, 'district' => $lga->senatorialDistrict->name])">
        <dl class="mt-6 flex flex-wrap gap-6 text-sm">
            <div><dt class="text-muted">{{ __('geography.wards') }}</dt><dd class="font-display text-2xl font-semibold text-fg">{{ $lga->wards_count ?: '—' }}</dd></div>
            <div><dt class="text-muted">{{ __('geography.polling_units') }}</dt><dd class="font-display text-2xl font-semibold text-fg">{{ $lga->polling_units_count ?: '—' }}</dd></div>
            @if ($lga->headquarters) <div><dt class="text-muted">{{ __('public.headquarters') }}</dt><dd class="font-display text-2xl font-semibold text-fg">{{ $lga->headquarters }}</dd></div> @endif
        </dl>
    </x-public.band>
    <div class="mx-auto max-w-6xl space-y-12 px-5 py-12 sm:px-8">
        @if ($page) <x-public.markdown :text="$page->live('body')" class="max-w-3xl" /> @endif
        <section>
            <h2 class="font-display text-xl font-semibold text-fg">{{ __('public.projects_in', ['lga' => $lga->name]) }}</h2>
            @if ($projects->isEmpty())
                <x-public.placeholder class="mt-4" :title="__('public.no_projects')" />
            @else
                <div class="mt-4 grid gap-5 md:grid-cols-3 [&>*]:min-w-0">@foreach ($projects as $project) <x-public.project-card :project="$project" /> @endforeach</div>
            @endif
        </section>
        <section>
            <h2 class="font-display text-xl font-semibold text-fg">{{ __('public.events_in', ['lga' => $lga->name]) }}</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse ($events as $event) <x-public.event-card :event="$event" /> @empty <x-public.placeholder :title="__('public.no_events')" /> @endforelse
            </div>
        </section>
        <section class="flex flex-col gap-4 rounded-2xl bg-primary-soft/60 p-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-medium text-fg">{{ __('public.find_pu_in', ['lga' => $lga->name]) }}</p>
            <a href="{{ route('public.polling-unit', ['lgaId' => $lga->id]) }}" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">{{ __('public.nav_polling_unit') }} <x-icon name="arrow-right" class="size-4" /></a>
        </section>
    </div>
</x-layouts::public>
