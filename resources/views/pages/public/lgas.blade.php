<x-layouts::public :title="__('public.nav_lgas')" :description="__('public.lgas_intro')">
    <x-public.band :title="__('public.lgas_title')" :eyebrow="__('public.nav_lgas')" :description="__('public.lgas_intro')" />
    <div class="mx-auto max-w-6xl space-y-10 px-5 py-12 sm:px-8">
        @foreach ($districts as $district)
            <section>
                <h2 class="font-display text-xl font-semibold text-fg">{{ $district->name }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($district->lgas as $lga)
                        <a href="{{ route('public.lgas.show', $lga->slug) }}" class="group rounded-xl border border-line bg-surface p-4 transition hover:border-line-strong hover:shadow-sm">
                            <span class="block font-semibold text-fg group-hover:text-primary">{{ $lga->name }}</span>
                            <span class="mt-1 block text-xs text-muted">{{ trans_choice('geography.count_wards', $lga->wards_count, ['count' => $lga->wards_count]) }} · {{ trans_choice('public.count_projects', (int) ($projectCounts[$lga->id] ?? 0), ['count' => (int) ($projectCounts[$lga->id] ?? 0)]) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts::public>
