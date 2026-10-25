@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('settings.title')" :description="__('settings.intro')" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
        <div class="space-y-6">
            @foreach ($definitions as $group => $items)
                <x-ui.card :title="__('settings.group_'.$group)" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($items as $setting)
                            <li class="px-5 py-4" wire:key="s-{{ $setting['field'] }}">
                                <form wire:submit="save('{{ $setting['key'] }}')" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div class="min-w-0 flex-1">
                                        <label for="setting-{{ $setting['field'] }}" class="text-sm font-medium text-fg">{{ $setting['label'] }}</label>
                                        <p class="text-xs text-subtle">
                                            {{ __('settings.default', ['value' => is_bool($setting['default']) ? ($setting['default'] ? __('settings.on') : __('settings.off')) : $setting['default']]) }}
                                            @isset($setting['min']) · {{ __('settings.range', ['min' => $setting['min'], 'max' => $setting['max']]) }} @endisset
                                        </p>
                                        @isset($setting['help']) <p class="mt-1 text-xs text-muted">{{ $setting['help'] }}</p> @endisset
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($setting['type'] === 'boolean')
                                            <input id="setting-{{ $setting['field'] }}" type="checkbox" wire:model="values.{{ $setting['field'] }}" class="size-5 accent-[var(--c-primary)]">
                                        @else
                                            <input id="setting-{{ $setting['field'] }}" type="{{ in_array($setting['type'], ['integer', 'decimal'], true) ? 'number' : 'text' }}" @if ($setting['type'] === 'decimal') step="0.01" @endif @isset($setting['min']) min="{{ $setting['min'] }}" max="{{ $setting['max'] }}" @endisset
                                                   wire:model="values.{{ $setting['field'] }}" class="form-control w-32 tabular-nums" @error('values.'.$setting['field']) aria-invalid="true" @enderror>
                                        @endif
                                        <x-ui.button type="submit" variant="secondary" loading="save('{{ $setting['key'] }}')">{{ __('ui.save') }}</x-ui.button>
                                    </div>
                                </form>
                                @error('values.'.$setting['field']) <p class="mt-1.5 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
            <x-ui.alert tone="info">{{ __('settings.more_help') }}</x-ui.alert>
        </div>

        <x-ui.card :title="__('settings.history')" :padding="false">
            <ul class="divide-y divide-line">
                @forelse ($history as $entry)
                    <li class="px-5 py-3 text-sm">
                        <p class="text-fg">{{ ((array) config('settings'))[str_replace('setting:', '', (string) $entry->subject_type)]['label'] ?? $entry->subject_type }}</p>
                        <p class="text-xs text-muted">
                            <span class="tabular-nums">{{ json_encode($entry->changes['from'] ?? null) }} → {{ json_encode($entry->changes['to'] ?? null) }}</span>
                            · {{ $entry->actor?->name ?? __('tasks.system') }} · {{ $entry->occurred_at->timezone($tz)->format('j M Y, H:i') }}
                        </p>
                    </li>
                @empty
                    <li><x-ui.empty-state compact icon="settings" :title="__('settings.no_history')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
