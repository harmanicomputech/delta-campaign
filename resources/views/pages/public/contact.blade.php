<x-layouts::public :title="__('public.contact_title')" :description="__('public.contact_intro')">
    <x-public.band :title="__('public.contact_title')" :description="__('public.contact_intro')" />
    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        <livewire:public-site.contact-form />
    </div>
</x-layouts::public>
