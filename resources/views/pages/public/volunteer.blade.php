<x-layouts::public :title="__('public.volunteer_title')" :description="__('public.volunteer_intro')">
    <x-public.band :title="__('public.volunteer_title')" :description="__('public.volunteer_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        <livewire:public-site.volunteer-signup />
    </div>
</x-layouts::public>
