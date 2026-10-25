<x-layouts::public :title="__('public.join_title')" :description="__('public.join_intro')">
    <x-public.band :title="__('public.join_title')" :description="__('public.join_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        <livewire:public-site.supporter-signup />
    </div>
</x-layouts::public>
