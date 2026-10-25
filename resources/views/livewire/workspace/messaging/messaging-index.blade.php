@php($tz = config('campaign.display_timezone'))
@php($used = $budget['limit'] > 0 ? min(100, (int) round($budget['spent'] / $budget['limit'] * 100)) : 0)
<div>
    <x-ui.page-header :title="__('messaging.title')" :description="__('messaging.intro')">
        <x-slot:actions>
            @can('messaging.templates')
                @if ($tab === 'templates')
                    <x-ui.button variant="secondary" icon="plus" wire:click="editTemplate">{{ __('messaging.new_template') }}</x-ui.button>
                @endif
            @endcan
            @can('messaging.create')
                <x-ui.button icon="send" :href="route('workspace.messaging.create')">{{ __('messaging.new_campaign') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] [&>*]:min-w-0">
        <x-ui.card>
            <p class="text-[0.8125rem] font-medium text-muted">{{ __('messaging.budget_month') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-fg">₦{{ number_format($budget['spent'], 2) }} <span class="text-base font-normal text-subtle">/ ₦{{ number_format($budget['limit']) }}</span></p>
            <x-ui.progress class="mt-3" :value="$used" :max="100" :tone="$used >= 90 ? 'danger' : ($used >= 70 ? 'warning' : 'brand')" :label="__('messaging.budget_used', ['pct' => $used])" />
            <p class="mt-1.5 text-xs text-subtle">{{ __('messaging.budget_help') }}</p>
        </x-ui.card>
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['campaigns' => __('messaging.campaigns'), 'templates' => __('messaging.templates'), 'health' => __('messaging.provider_health')] as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}"
                            @class(['rounded-lg px-3.5 py-2 text-sm font-medium transition-colors', 'bg-primary-soft text-primary-soft-fg' => $tab === $key, 'text-muted hover:bg-surface-sunken hover:text-fg' => $tab !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <p class="mt-3 text-[0.8125rem] text-muted">{{ __('messaging.rules') }}</p>
        </x-ui.card>
    </div>

    @if ($tab === 'campaigns')
        <x-ui.card :padding="false">
            <div class="flex items-center gap-3 border-b border-line px-5 py-3">
                <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 w-auto pr-8">
                    <option value="">{{ __('supporters.all_statuses') }}</option>
                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            @if ($campaignList->isEmpty())
                <x-ui.empty-state icon="send" :title="__('messaging.no_campaigns')" :description="__('messaging.no_campaigns_help')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($campaignList as $campaign)
                        <li wire:key="c-{{ $campaign->id }}">
                            <a href="{{ route('workspace.messaging.show', $campaign) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-fg">{{ $campaign->name }}</span>
                                        <x-ui.badge size="sm" :icon="$campaign->channel->value === 'whatsapp' ? 'message-square' : 'smartphone'">{{ $campaign->channel->label() }}</x-ui.badge>
                                    </span>
                                    <span class="block text-xs text-muted">{{ $campaign->template?->name }} · {{ $campaign->creator?->name }} · {{ ($campaign->scheduled_at ?? $campaign->created_at)->timezone($tz)->format('j M Y, H:i') }}</span>
                                </span>
                                <span class="text-xs tabular-nums text-muted">
                                    @if ($campaign->started_at)
                                        {{ __('messaging.progress_short', ['sent' => number_format($campaign->sent_count), 'delivered' => number_format($campaign->delivered_count), 'failed' => number_format($campaign->failed_count)]) }}
                                    @else
                                        {{ trans_choice('messaging.recipients_count', $campaign->estimated_recipients, ['count' => number_format($campaign->estimated_recipients)]) }} · ₦{{ number_format((float) $campaign->estimated_cost, 2) }}
                                    @endif
                                </span>
                                <x-ui.badge :tone="$campaign->status->tone()" size="sm" dot>{{ $campaign->status->label() }}</x-ui.badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line">{{ $campaignList->links() }}</div>
            @endif
        </x-ui.card>
    @elseif ($tab === 'templates')
        @if ($templates->isEmpty())
            <x-ui.card><x-ui.empty-state icon="file-text" :title="__('messaging.no_templates')" :description="__('messaging.no_templates_help')" /></x-ui.card>
        @else
            <div class="grid gap-4 lg:grid-cols-2 [&>*]:min-w-0">
                @foreach ($templates as $template)
                    @php($segments = $renderer->segments($renderer->worstCase($template->body_en)))
                    <x-ui.card wire:key="t-{{ $template->id }}" :class="$template->status === 'archived' ? 'opacity-60' : ''">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-fg">{{ $template->name }}</p>
                                <p class="text-xs text-muted">{{ $template->channel->label() }} · {{ $template->purpose->label() }}@if ($template->provider_template) · <span class="font-mono">{{ $template->provider_template }}</span>@endif</p>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($template->status === 'archived') <x-ui.badge size="sm">{{ __('messaging.archived') }}</x-ui.badge> @endif
                                @can('messaging.templates')
                                    @if ($template->status === 'active')
                                        <x-ui.button size="sm" variant="ghost" square icon="pencil" wire:click="editTemplate({{ $template->id }})"><span class="sr-only">{{ __('ui.edit') }}</span></x-ui.button>
                                        <x-ui.button size="sm" variant="ghost" square icon="archive" wire:click="archiveTemplate({{ $template->id }})" wire:confirm="{{ __('messaging.archive_confirm') }}"><span class="sr-only">{{ __('messaging.archive') }}</span></x-ui.button>
                                    @endif
                                @endcan
                            </div>
                        </div>
                        <p class="mt-3 rounded-lg bg-surface-sunken px-3 py-2.5 text-sm whitespace-pre-line text-fg">{{ $template->body_en }}</p>
                        @if ($template->body_pcm)
                            <p class="mt-2 rounded-lg bg-surface-sunken px-3 py-2.5 text-sm whitespace-pre-line text-muted"><span class="mr-1 text-xs font-semibold text-subtle">Naijá</span>{{ $template->body_pcm }}</p>
                        @endif
                        @if ($template->channel->value === 'sms')
                            <p class="mt-2 text-xs text-subtle">{{ trans_choice('messaging.segments', $segments, ['count' => $segments]) }} · {{ $renderer->isGsm($template->body_en) ? 'GSM-7' : 'Unicode' }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @else
        <div class="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
            @foreach ($health as $channel => $h)
                <x-ui.card :title="$channel === 'sms' ? 'SMS' : 'WhatsApp'" :description="__('messaging.driver', ['driver' => $providers[$channel]])">
                    <x-slot:actions>
                        <x-ui.badge :tone="['healthy' => 'success', 'idle' => 'neutral', 'degraded' => 'warning', 'down' => 'danger'][$h['status']]" dot>{{ __('messaging.health_'.$h['status']) }}</x-ui.badge>
                    </x-slot:actions>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div><dt class="text-xs text-muted">{{ __('messaging.sent_hour') }}</dt><dd class="mt-0.5 font-display text-xl font-semibold tabular-nums text-fg">{{ number_format($h['sent']) }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ __('messaging.delivered') }}</dt><dd class="mt-0.5 font-display text-xl font-semibold tabular-nums text-fg">{{ number_format($h['delivered']) }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ __('messaging.failure_rate') }}</dt><dd class="mt-0.5 font-display text-xl font-semibold tabular-nums text-fg">{{ $h['failure_rate'] }}%</dd></div>
                    </dl>
                    @if ($h['tripped_for'] > 0)
                        <x-ui.alert tone="danger" class="mt-4">{{ __('messaging.tripped', ['minutes' => (int) ceil($h['tripped_for'] / 60)]) }}</x-ui.alert>
                    @endif
                    @if ($h['last_error'])
                        <p class="mt-3 text-xs text-subtle">{{ __('messaging.last_error') }}: <span class="font-mono">{{ $h['last_error'] }}</span></p>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @can('messaging.templates')
        <x-ui.modal name="template-form" :title="$editingId ? __('messaging.edit_template') : __('messaging.new_template')" :description="__('messaging.template_help')" max-width="2xl" icon="file-text">
            <form wire:submit="saveTemplate" id="template-form" class="space-y-4">
                <x-ui.input wire:model="tName" :label="__('messaging.template_name')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="tChannel" :label="__('messaging.channel')" :options="['sms' => 'SMS', 'whatsapp' => 'WhatsApp']" />
                    <x-ui.select wire:model="tPurpose" :label="__('messaging.purpose')" :options="$purposes" />
                </div>
                @if ($tChannel === 'whatsapp')
                    <x-ui.input wire:model="tProviderTemplate" :label="__('messaging.provider_template')" :hint="__('messaging.provider_template_help')" required />
                @endif
                @php($len = $renderer->segments($renderer->worstCase($tBodyEn)))
                <x-ui.textarea wire:model.live.debounce.400ms="tBodyEn" :label="__('messaging.body_en')" :hint="__('messaging.body_hint').($tChannel === 'sms' && $tBodyEn !== '' ? ' · '.trans_choice('messaging.segments', $len, ['count' => $len]) : '')" rows="4" required />
                <x-ui.textarea wire:model="tBodyPcm" :label="__('messaging.body_pcm')" rows="4" optional />
                @error('tChannel') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="template-form" loading="saveTemplate">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
