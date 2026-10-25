<div>
    <x-ui.page-header :title="$projectId ? __('projects.edit') : __('projects.new')" :description="__('projects.editor_help')" />
    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <x-ui.card>
            <div class="space-y-4">
                <x-ui.input wire:model="title" :label="__('projects.project_title')" required />
                <x-ui.textarea wire:model="description" :label="__('projects.description')" :hint="__('content.body_hint')" rows="10" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="category" :label="__('public.category')" :options="$categories" />
                    <x-ui.select wire:model="verification" :label="__('projects.verification')" :options="\App\Enums\ProjectVerification::options()" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="__('tasks.statewide')" :options="$lgas->all()" optional />
                    <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="__('tasks.whole_lga')" :options="$wards->all()" :disabled="$wards->isEmpty()" optional />
                </div>
                <x-ui.input wire:model="location" :label="__('public.location')" optional />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input type="date" wire:model="startDate" :label="__('projects.start_date')" optional />
                    <x-ui.input type="date" wire:model="endDate" :label="__('projects.end_date')" optional />
                </div>
                <x-ui.textarea wire:model="sourceNote" :label="__('projects.source_note')" :hint="__('projects.source_note_hint')" rows="2" required />
            </div>
        </x-ui.card>
        <div class="space-y-6">
            <x-ui.card :title="__('content.cover')">
                @if ($cover) <img src="{{ $cover->url(480) }}" alt="" class="mb-3 aspect-video w-full rounded-lg object-cover"> @endif
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong px-4 py-3 text-sm font-medium text-muted hover:border-primary hover:text-primary">
                    <x-icon name="upload" class="size-4" /><span wire:loading.remove wire:target="image">{{ __('content.upload_image') }}</span><span wire:loading wire:target="image">{{ __('ui.loading') }}</span>
                    <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <p class="mt-2 text-xs text-subtle">{{ __('content.image_help') }}</p>
            </x-ui.card>
            <x-ui.alert tone="warning">{{ __('projects.no_invention') }}</x-ui.alert>
            <x-ui.button type="submit" class="w-full" loading="save">{{ __('content.save_draft') }}</x-ui.button>
        </div>
    </form>
</div>
