@php($key = $type->value === 'news' ? 'news' : 'fact_checks')
<x-layouts::public :title="__('public.nav_'.$key)" :description="__('public.'.$key.'_intro')">
    <x-public.band :title="__('public.nav_'.$key)" :description="__('public.'.$key.'_intro')" />
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        @if ($items->isEmpty())
            <x-public.placeholder :title="__('public.no_'.$key)" />
        @else
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3 [&>*]:min-w-0">@foreach ($items as $item) <x-public.article-card :item="$item" /> @endforeach</div>
            <div class="mt-8">{{ $items->links() }}</div>
        @endif
    </div>
</x-layouts::public>
