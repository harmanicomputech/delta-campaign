<x-layouts::public :title="__('public.pu_title')" :description="__('public.pu_intro')">
    <x-public.band :title="__('public.pu_title')" :description="__('public.pu_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        <livewire:public-site.polling-unit-lookup />
    </div>
</x-layouts::public>
