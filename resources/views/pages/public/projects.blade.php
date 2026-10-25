<x-layouts::public :title="__('public.nav_projects')" :description="__('public.projects_intro')">
    <x-public.band :title="__('public.project_directory')" :eyebrow="__('public.nav_projects')" :description="__('public.projects_intro')">
        <form method="GET" class="mt-6 grid gap-3 sm:flex sm:items-end" role="search">
            <div><label for="f-lga" class="text-[0.8125rem] font-medium text-fg">LGA</label>
                <select id="f-lga" name="lga" class="form-control mt-1.5 sm:w-56"><option value="">{{ __('supporters.all_lgas') }}</option>@foreach ($lgas as $slug => $name)<option value="{{ $slug }}" @selected($lga === $slug)>{{ $name }}</option>@endforeach</select></div>
            <div><label for="f-cat" class="text-[0.8125rem] font-medium text-fg">{{ __('public.category') }}</label>
                <select id="f-cat" name="category" class="form-control mt-1.5 sm:w-56"><option value="">{{ __('public.all_categories') }}</option>@foreach (\App\Models\Project::CATEGORIES as $c)<option value="{{ $c }}" @selected($category === $c)>{{ __('projects.category_'.$c) }}</option>@endforeach</select></div>
            <x-ui.button type="submit" variant="secondary">{{ __('public.filter') }}</x-ui.button>
        </form>
    </x-public.band>
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <x-ui.alert tone="info" class="mb-8">{{ __('public.projects_source_note') }}</x-ui.alert>
        @if ($projects->isEmpty())
            <x-public.placeholder :title="__('public.no_projects')">{{ __('public.no_projects_help') }}</x-public.placeholder>
        @else
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3 [&>*]:min-w-0">@foreach ($projects as $project) <x-public.project-card :project="$project" /> @endforeach</div>
            <div class="mt-8">{{ $projects->links() }}</div>
        @endif
    </div>
</x-layouts::public>
