<div x-data="{ lang: 'en', preview: false }">
    <x-ui.page-header :title="$itemId ? __('content.edit') : __('content.new_'.$type)" :description="__('content.editor_help')" />

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <x-ui.card :padding="false">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                <div class="inline-flex rounded-lg border border-line bg-surface-sunken p-0.5" role="tablist" aria-label="{{ __('supporters.language') }}">
                    <button type="button" role="tab" x-on:click="lang = 'en'" :aria-selected="lang === 'en'" class="rounded-md px-3 py-1.5 text-[0.8125rem] font-medium" :class="lang === 'en' ? 'bg-surface text-fg shadow-xs' : 'text-subtle'">English</button>
                    <button type="button" role="tab" x-on:click="lang = 'pcm'" :aria-selected="lang === 'pcm'" class="rounded-md px-3 py-1.5 text-[0.8125rem] font-medium" :class="lang === 'pcm' ? 'bg-surface text-fg shadow-xs' : 'text-subtle'">Naijá</button>
                </div>
                <x-ui.checkbox x-model="preview" :label="__('content.preview')" id="toggle-preview" />
            </div>
            <div class="space-y-4 p-5" x-show="lang === 'en'">
                <x-ui.input wire:model.blur="titleEn" :label="__('content.title_en')" required />
                <x-ui.textarea wire:model="summaryEn" :label="__('content.summary')" :hint="__('content.summary_hint')" rows="2" optional />
                <div x-show="! preview"><x-ui.textarea wire:model.live.debounce.800ms="bodyEn" :label="__('content.body_en')" :hint="__('content.body_hint')" rows="16" required /></div>
                <div x-show="preview" x-cloak class="rounded-xl border border-line p-6"><x-public.markdown :text="$preview" /></div>
            </div>
            <div class="space-y-4 p-5" x-show="lang === 'pcm'" x-cloak>
                <x-ui.alert tone="info">{{ __('content.pcm_help') }}</x-ui.alert>
                <x-ui.input wire:model="titlePcm" :label="__('content.title_pcm')" optional />
                <x-ui.textarea wire:model="summaryPcm" :label="__('content.summary')" rows="2" optional />
                <x-ui.textarea wire:model="bodyPcm" :label="__('content.body_pcm')" rows="16" optional />
            </div>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card :title="__('content.settings')">
                <div class="space-y-4">
                    <x-ui.input wire:model="slug" :label="__('content.slug')" :hint="__('content.slug_hint')" optional />
                    @if ($type === 'page')
                        <x-ui.select wire:model="lgaId" :label="__('content.lga_page')" :hint="__('content.lga_page_hint')" :placeholder="__('content.not_lga')" :options="$lgas->all()" optional />
                    @endif
                    <div>
                        <p class="text-[0.8125rem] font-medium text-fg">{{ __('content.cover') }}</p>
                        @if ($cover) <img src="{{ $cover->url(480) }}" alt="{{ $cover->alt() }}" class="mt-2 aspect-video w-full rounded-lg object-cover"> @endif
                        <x-ui.input wire:model="altEn" :label="__('content.alt')" :hint="__('content.alt_hint')" class="mt-2" optional />
                        <label class="mt-2 flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong px-4 py-3 text-sm font-medium text-muted hover:border-primary hover:text-primary">
                            <x-icon name="upload" class="size-4" />
                            <span wire:loading.remove wire:target="image">{{ $cover ? __('content.replace_image') : __('content.upload_image') }}</span>
                            <span wire:loading wire:target="image">{{ __('ui.loading') }}</span>
                            <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>
                        @error('image') <p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-subtle">{{ __('content.image_help') }}</p>
                    </div>
                    @if ($itemId) <x-ui.input wire:model="note" :label="__('content.change_note')" optional /> @endif
                </div>
            </x-ui.card>
            <div class="flex flex-col gap-2">
                <x-ui.button type="submit" variant="secondary" loading="save">{{ __('content.save_draft') }}</x-ui.button>
                <x-ui.button type="button" icon="send" wire:click="submitForReview" loading="submitForReview">{{ __('content.submit_review') }}</x-ui.button>
                <p class="text-xs text-subtle">{{ __('content.workflow_help') }}</p>
            </div>
        </div>
    </form>
</div>
