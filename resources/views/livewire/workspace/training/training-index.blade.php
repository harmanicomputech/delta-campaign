<div>
    <x-ui.page-header :title="__('training.title')" :description="__('training.intro')">
        <x-slot:actions>
            @if ($manager)
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-module')">{{ __('training.new') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($published > 0)
        <x-ui.card class="mb-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-fg">{{ __('training.progress', ['done' => $passed, 'total' => $published]) }}</p>
                    <p class="text-[0.8125rem] text-muted">{{ __('training.progress_help') }}</p>
                </div>
                <x-ui.progress :value="$passed" :max="$published" tone="success" class="sm:w-64" :label="__('training.progress', ['done' => $passed, 'total' => $published])" />
            </div>
        </x-ui.card>
    @endif

    @if ($modules->isEmpty())
        <x-ui.card><x-ui.empty-state icon="graduation-cap" :title="__('training.none')" :description="$manager ? __('training.none_manager') : __('training.none_help')" /></x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 [&>*]:min-w-0">
            @foreach ($modules as $module)
                @php($result = $results->get($module->id))
                <a href="{{ route('workspace.training.show', $module) }}" wire:key="m-{{ $module->id }}"
                   class="group flex flex-col rounded-xl border border-line bg-surface p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-line-strong hover:shadow-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20">
                    <div class="mb-3 flex flex-wrap items-center gap-1.5">
                        @if (! $module->isPublished()) <x-ui.badge tone="warning" size="sm">{{ __('training.draft') }}</x-ui.badge> @endif
                        @if (str_starts_with($module->slug, 'sample-')) <x-ui.sample-badge /> @endif
                        <x-ui.badge size="sm">{{ __('training.audience_'.$module->audience) }}</x-ui.badge>
                        @if ($module->language === 'pcm') <x-ui.badge size="sm" tone="info">Naijá</x-ui.badge> @endif
                    </div>
                    <h2 class="font-display text-lg font-semibold tracking-tight text-fg group-hover:text-primary">{{ $module->title }}</h2>
                    <p class="mt-1.5 line-clamp-3 flex-1 text-sm text-muted">{{ $module->summary }}</p>
                    <div class="mt-4 flex items-center justify-between border-t border-line pt-3 text-xs text-subtle">
                        <span>{{ trans_choice('training.question_count', $module->questions_count, ['count' => $module->questions_count]) }} · {{ __('training.pass_at', ['mark' => $module->pass_mark]) }}</span>
                        @if ($result)
                            <x-ui.badge :tone="$result->passed ? 'success' : 'warning'" size="sm" :icon="$result->passed ? 'check' : null">{{ $result->score }}%</x-ui.badge>
                        @else
                            <span class="inline-flex items-center gap-1 font-medium text-primary">{{ __('training.start') }} <x-icon name="arrow-right" class="size-3.5" /></span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($manager)
        <x-ui.modal name="new-module" :title="__('training.new')" :description="__('training.new_help')" max-width="2xl" icon="graduation-cap">
            <form wire:submit="create" id="new-module-form" class="space-y-4">
                <x-ui.input wire:model="title" :label="__('training.module_title')" required />
                <x-ui.textarea wire:model="summary" :label="__('training.summary')" rows="2" required />
                <x-ui.textarea wire:model="body" :label="__('training.body')" :hint="__('training.body_hint')" rows="8" required />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select wire:model="audience" :label="__('training.audience')" :options="['all' => __('training.audience_all'), 'volunteer' => __('training.audience_volunteer'), 'polling_agent' => __('training.audience_polling_agent')]" />
                    <x-ui.select wire:model="language" :label="__('supporters.language')" :options="['en' => 'English', 'pcm' => 'Naijá (Pidgin)']" />
                    <x-ui.input type="number" min="0" max="100" wire:model="passMark" :label="__('training.pass_mark')" />
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="new-module-form" loading="create">{{ __('training.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
