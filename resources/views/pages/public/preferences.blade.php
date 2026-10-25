<x-layouts::public :title="__('public.prefs_title')" :description="__('public.prefs_intro')">
    <x-public.band :title="__('public.prefs_title')" :description="__('public.prefs_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        <livewire:public-site.preferences-centre />
    </div>
</x-layouts::public>
