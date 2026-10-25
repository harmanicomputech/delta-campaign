<div>
    <x-ui.page-header :title="$eventId ? __('events.edit') : __('events.new')" :description="__('events.editor_help')" />
    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <x-ui.card>
            <div class="space-y-4">
                <x-ui.input wire:model="titleEn" :label="__('content.title_en')" required />
                <x-ui.input wire:model="titlePcm" :label="__('content.title_pcm')" optional />
                <x-ui.textarea wire:model="descriptionEn" :label="__('events.description')" :hint="__('content.body_hint')" rows="6" required />
                <x-ui.textarea wire:model="descriptionPcm" :label="__('events.description_pcm')" rows="4" optional />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input type="datetime-local" wire:model="startsAt" :label="__('events.starts_at')" required />
                    <x-ui.input type="datetime-local" wire:model="endsAt" :label="__('events.ends_at')" optional />
                </div>
                <x-ui.input wire:model="venue" :label="__('events.venue')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="$statewide ? __('tasks.statewide') : null" :options="$lgas->all()" :optional="$statewide" />
                    <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="__('tasks.whole_lga')" :options="$wards->all()" :disabled="$wards->isEmpty()" optional />
                </div>
            </div>
        </x-ui.card>
        <div class="space-y-6">
            <x-ui.card :title="__('events.registration')">
                <div class="space-y-4">
                    <x-ui.input type="number" min="1" wire:model="capacity" :label="__('events.capacity')" :hint="__('events.capacity_hint')" optional />
                    <x-ui.checkbox wire:model="registrationOpen" :label="__('events.registration_open')" />
                </div>
            </x-ui.card>
            <x-ui.card :title="__('content.cover')">
                @if ($cover) <img src="{{ $cover->url(480) }}" alt="" class="mb-3 aspect-video w-full rounded-lg object-cover"> @endif
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong px-4 py-3 text-sm font-medium text-muted hover:border-primary hover:text-primary">
                    <x-icon name="upload" class="size-4" /><span wire:loading.remove wire:target="image">{{ __('content.upload_image') }}</span><span wire:loading wire:target="image">{{ __('ui.loading') }}</span>
                    <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <p class="mt-2 text-xs text-subtle">{{ __('content.image_help') }}</p>
            </x-ui.card>
            <x-ui.button type="submit" class="w-full" loading="save">{{ __('events.save') }}</x-ui.button>
        </div>
    </form>
</div>
