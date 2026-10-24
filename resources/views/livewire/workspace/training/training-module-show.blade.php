@php($manager = auth()->user()->can('training.manage'))
<div>
    <x-ui.page-header :title="$module->title" :description="$module->summary" :eyebrow="__('training.module')">
        <x-slot:actions>
            @if (! $module->isPublished()) <x-ui.badge tone="warning" dot>{{ __('training.draft') }}</x-ui.badge> @endif
            @if (str_starts_with($module->slug, 'sample-')) <x-ui.sample-badge /> @endif
            @if ($manager)
                <x-ui.button variant="secondary" icon="pencil" x-on:click="$dispatch('open-modal', 'edit-module')">{{ __('training.edit') }}</x-ui.button>
                <x-ui.button :variant="$module->isPublished() ? 'ghost' : 'primary'" :icon="$module->isPublished() ? 'eye-off' : 'send'" wire:click="togglePublished" loading="togglePublished">{{ $module->isPublished() ? __('training.unpublish') : __('training.publish') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px] [&>*]:min-w-0">
        <x-ui.card>
            <article class="rich-text max-w-[68ch]">{!! $html !!}</article>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card :title="__('training.quiz')" :description="__('training.quiz_help', ['mark' => $module->pass_mark])">
                @if ($manager)
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" icon="plus" x-on:click="$dispatch('open-modal', 'add-question')">{{ __('training.add_question') }}</x-ui.button>
                    </x-slot:actions>
                @endif

                @if ($attempt)
                    <div @class(['rounded-lg border p-4 text-center', 'border-success/30 bg-success-soft' => $attempt->passed, 'border-warning/30 bg-warning-soft' => ! $attempt->passed]) role="status">
                        <p class="font-display text-3xl font-semibold tabular-nums {{ $attempt->passed ? 'text-success' : 'text-warning' }}">{{ $attempt->score }}%</p>
                        <p class="mt-1 text-sm font-medium text-fg">{{ $attempt->passed ? __('training.passed') : __('training.not_passed') }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $attempt->passed ? __('training.passed_help') : __('training.retry_help') }}</p>
                        <x-ui.button class="mt-3" size="sm" variant="secondary" icon="rotate-ccw" wire:click="retry">{{ __('training.retry') }}</x-ui.button>
                    </div>
                @elseif ($questions->isEmpty())
                    <x-ui.empty-state compact icon="circle-help" :title="__('training.no_questions')" />
                @else
                    @if ($best)
                        <x-ui.alert :tone="$best->passed ? 'success' : 'info'" class="mb-4">{{ __('training.best', ['score' => $best->score]) }}</x-ui.alert>
                    @endif
                    <form wire:submit="submit" class="space-y-5">
                        @foreach ($questions as $i => $question)
                            <fieldset wire:key="q-{{ $question->id }}">
                                <legend class="flex w-full items-start justify-between gap-2 text-sm font-semibold text-fg">
                                    <span>{{ $i + 1 }}. {{ $question->prompt }}</span>
                                    @if ($manager)
                                        <button type="button" wire:click="removeQuestion({{ $question->id }})" wire:confirm="{{ __('training.remove_question_confirm') }}" class="rounded p-1 text-subtle hover:bg-danger-soft hover:text-danger">
                                            <x-icon name="trash-2" class="size-4" /><span class="sr-only">{{ __('training.remove_question') }}</span>
                                        </button>
                                    @endif
                                </legend>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($question->options as $index => $option)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-3 py-2.5 text-sm transition-colors hover:border-line-strong has-[:checked]:border-primary has-[:checked]:bg-primary-soft/50">
                                            <input type="radio" name="q{{ $question->id }}" value="{{ $index }}" wire:model="answers.{{ $question->id }}" class="size-4 accent-[var(--c-primary)]" @disabled(! $canTake)>
                                            <span class="flex-1 text-fg">{{ $option }}</span>
                                            @if ($manager && $question->correct_index === $index) <x-ui.badge tone="success" size="sm">{{ __('training.correct') }}</x-ui.badge> @endif
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                        @error('answers') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                        @if ($canTake)
                            <x-ui.button type="submit" class="w-full" loading="submit">{{ __('training.submit') }}</x-ui.button>
                        @else
                            <p class="text-xs text-subtle">{{ $module->isPublished() ? __('training.preview_only') : __('training.publish_first') }}</p>
                        @endif
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($manager)
        <x-ui.modal name="edit-module" :title="__('training.edit')" max-width="2xl" icon="pencil">
            <form wire:submit="save" id="edit-module-form" class="space-y-4">
                <x-ui.input wire:model="title" :label="__('training.module_title')" required />
                <x-ui.textarea wire:model="summary" :label="__('training.summary')" rows="2" required />
                <x-ui.textarea wire:model="body" :label="__('training.body')" :hint="__('training.body_hint')" rows="10" required />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select wire:model="audience" :label="__('training.audience')" :options="['all' => __('training.audience_all'), 'volunteer' => __('training.audience_volunteer'), 'polling_agent' => __('training.audience_polling_agent')]" />
                    <x-ui.select wire:model="language" :label="__('supporters.language')" :options="['en' => 'English', 'pcm' => 'Naijá (Pidgin)']" />
                    <x-ui.input type="number" min="0" max="100" wire:model="passMark" :label="__('training.pass_mark')" />
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="edit-module-form" loading="save">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="add-question" :title="__('training.add_question')" max-width="xl" icon="circle-help">
            <form wire:submit="addQuestion" id="add-question-form" class="space-y-4">
                <x-ui.input wire:model="prompt" :label="__('training.question')" required />
                <x-ui.textarea wire:model.live.debounce.300ms="options" :label="__('training.options')" :hint="__('training.options_hint')" rows="4" required />
                <x-ui.select wire:model="correctIndex" :label="__('training.correct_answer')" :options="$pending" :disabled="$pending === []" />
                @error('correctIndex') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="add-question-form" loading="addQuestion">{{ __('training.add_question') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
