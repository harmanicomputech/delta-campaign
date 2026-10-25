<div>
    <x-ui.page-header :title="$campaignId ? __('messaging.edit_campaign') : __('messaging.new_campaign')" :description="__('messaging.editor_intro')" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px] [&>*]:min-w-0">
        <form wire:submit="save" class="space-y-6">
            <x-ui.card :title="__('messaging.message')">
                <div class="space-y-4">
                    <x-ui.input wire:model="name" :label="__('messaging.campaign_name')" :hint="__('messaging.campaign_name_hint')" required />
                    <x-ui.select wire:model.live="templateId" :label="__('messaging.template')" :placeholder="__('users.choose')" :options="$templates->mapWithKeys(fn ($t) => [$t->id => $t->name.' · '.$t->channel->label().' · '.$t->purpose->label()])->all()" required />
                    @if ($templates->isEmpty())
                        <x-ui.alert tone="warning">{{ __('messaging.no_templates_help') }}</x-ui.alert>
                    @endif
                    <x-ui.input type="datetime-local" wire:model="scheduledAt" :label="__('messaging.schedule')" :hint="__('messaging.schedule_hint')" optional />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('messaging.audience')" :description="$statewide ? __('messaging.audience_help_state') : __('messaging.audience_help')">
                <div class="space-y-5">
                    @if ($lgas->isNotEmpty())
                        <fieldset>
                            <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('messaging.lgas') }} <span class="font-normal text-subtle">· {{ __('messaging.all_if_none') }}</span></legend>
                            <div class="grid max-h-56 grid-cols-2 gap-x-4 gap-y-1.5 overflow-y-auto rounded-lg border border-line p-3 scroll-thin sm:grid-cols-3">
                                @foreach ($lgas as $id => $label)
                                    <x-ui.checkbox wire:model.live="lgaIds" value="{{ $id }}" :label="$label" />
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    @if ($wards->isNotEmpty())
                        <fieldset>
                            <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('messaging.wards') }} <span class="font-normal text-subtle">· {{ __('messaging.all_if_none') }}</span></legend>
                            <div class="grid max-h-48 grid-cols-1 gap-y-1.5 overflow-y-auto rounded-lg border border-line p-3 scroll-thin sm:grid-cols-2">
                                @foreach ($wards as $id => $label)
                                    <x-ui.checkbox wire:model.live="wardIds" value="{{ $id }}" :label="$label" />
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    @error('lgaIds') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                    @error('wardIds') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                    <fieldset>
                        <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('messaging.verification') }} <span class="font-normal text-subtle">· {{ __('messaging.all_if_none') }}</span></legend>
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            @foreach ($statuses as $value => $label)
                                <x-ui.checkbox wire:model.live="verification" value="{{ $value }}" :label="$label" />
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select wire:model.live="language" :label="__('supporters.language')" :options="['' => __('messaging.any_language'), 'en' => 'English', 'pcm' => 'Naijá (Pidgin)']" />
                        <div class="sm:pt-7"><x-ui.checkbox wire:model.live="volunteersOnly" :label="__('messaging.volunteers_only')" /></div>
                    </div>
                </div>
            </x-ui.card>

            @error('audience') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror

            <div class="flex flex-wrap justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('workspace.messaging.index')">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="secondary" loading="save">{{ __('messaging.save_draft') }}</x-ui.button>
                <x-ui.button type="button" icon="send" wire:click="submit" loading="submit">{{ $estimate && ! $estimate['second'] && auth()->user()->can('messaging.approve') ? __('messaging.submit_send') : __('messaging.submit') }}</x-ui.button>
            </div>
        </form>

        <div class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <x-ui.card :title="__('messaging.estimate')" :description="__('messaging.estimate_help')">
                @if ($estimate === null)
                    <p class="text-sm text-muted">{{ __('messaging.choose_template_first') }}</p>
                @else
                    <dl class="space-y-2.5 text-sm" wire:loading.class="opacity-60">
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.matching') }}</dt><dd class="tabular-nums text-fg">{{ number_format($estimate['total']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.eligible', ['channel' => $template->channel->label()]) }}</dt><dd class="font-semibold tabular-nums text-fg">{{ number_format($estimate['eligible']) }}</dd></div>
                        @if ($template->channel->value === 'sms')
                            <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.segments_label') }}</dt><dd class="tabular-nums text-fg">{{ $estimate['segments'] }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.estimated_cost') }}</dt><dd class="tabular-nums text-fg">₦{{ number_format($estimate['cost'], 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.budget_remaining') }}</dt><dd @class(['tabular-nums', 'text-danger font-semibold' => $estimate['cost'] > $estimate['remaining'], 'text-fg' => $estimate['cost'] <= $estimate['remaining']])>₦{{ number_format($estimate['remaining'], 2) }}</dd></div>
                    </dl>
                    <x-ui.alert :tone="$estimate['second'] ? 'warning' : 'info'" class="mt-4">{{ $estimate['second'] ? __('messaging.needs_second_help', ['n' => number_format($threshold)]) : __('messaging.small_send_help', ['n' => number_format($threshold)]) }}</x-ui.alert>
                    @if ($estimate['total'] > $estimate['eligible'])
                        <p class="mt-3 text-xs text-subtle">{{ __('messaging.excluded_help', ['count' => number_format($estimate['total'] - $estimate['eligible'])]) }}</p>
                    @endif
                @endif
            </x-ui.card>

            @if ($estimate)
                <x-ui.card :title="__('messaging.preview')">
                    <x-slot:actions>
                        <select wire:model.live="previewLanguage" aria-label="{{ __('supporters.language') }}" class="form-control h-8 min-h-8 w-auto py-0 pr-8 text-xs">
                            <option value="en">English</option><option value="pcm">Naijá</option>
                        </select>
                    </x-slot:actions>
                    <div class="rounded-2xl rounded-bl-sm bg-surface-sunken px-4 py-3 text-sm whitespace-pre-line text-fg">{{ $estimate['preview'] }}</div>
                    <p class="mt-2 text-xs text-subtle">{{ __('messaging.preview_help') }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
