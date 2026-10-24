@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('privacy.title')" :description="__('privacy.intro')">
        <x-slot:actions>
            @can('dsr.manage')
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'record-request')">{{ __('privacy.record_request') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
        <x-ui.stat :label="__('privacy.open_requests')" :value="number_format($openCount)" icon="inbox" />
        <x-ui.stat :label="__('privacy.overdue')" :value="number_format($overdueCount)" icon="clock" :delta="$overdueCount > 0 ? '!' : null" delta-tone="danger" />
        <x-ui.stat :label="__('privacy.suppressions')" :value="number_format($activeSuppressions)" icon="lock-keyhole" />
        <x-ui.stat :label="__('privacy.notices')" :value="$currentNotice?->published_at->timezone($tz)->format('j M Y')" icon="file-text" :hint="$currentNotice ? __('privacy.current').': '.$currentNotice->version : __('supporters.no_notice')" />
    </section>

    <div role="tablist" class="mb-5 flex gap-1 overflow-x-auto border-b border-line">
        @foreach (['requests' => __('privacy.requests'), 'suppressions' => __('privacy.suppressions'), 'notices' => __('privacy.notices'), 'retention' => __('privacy.retention')] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['relative -mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors', 'border-primary text-fg' => $tab === $key, 'border-transparent text-subtle hover:text-fg' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'requests')
        <x-ui.card :padding="false">
            <div class="flex items-center gap-2 border-b border-line px-5 py-3">
                <select wire:model.live="requestFilter" aria-label="{{ __('privacy.status') }}" class="form-control h-9 min-h-9 w-auto pr-8">
                    <option value="open">{{ __('privacy.open_requests') }}</option>
                    <option value="overdue">{{ __('privacy.overdue') }}</option>
                    <option value="all">{{ __('supporters.all_statuses') }}</option>
                </select>
            </div>
            @if ($requests->isEmpty())
                <x-ui.empty-state icon="inbox" :title="__('privacy.no_requests')" :description="__('privacy.no_requests_help')" />
            @else
                <x-ui.table :caption="__('privacy.requests')">
                    <x-slot:head>
                        <tr>
                            <x-ui.th>{{ __('privacy.reference') }}</x-ui.th>
                            <x-ui.th>{{ __('privacy.type') }}</x-ui.th>
                            <x-ui.th class="hidden md:table-cell">{{ __('privacy.channel') }}</x-ui.th>
                            <x-ui.th>{{ __('privacy.status') }}</x-ui.th>
                            <x-ui.th class="hidden lg:table-cell">{{ __('privacy.received') }}</x-ui.th>
                            <x-ui.th align="right">{{ __('privacy.due', ['date' => '']) }}</x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($requests as $request)
                        <tr wire:key="r-{{ $request->id }}">
                            <x-ui.td><a href="{{ route('workspace.privacy.requests.show', $request) }}" class="font-mono text-sm font-medium hover:text-primary">{{ $request->reference }}</a></x-ui.td>
                            <x-ui.td>{{ $request->type->label() }}</x-ui.td>
                            <x-ui.td class="hidden text-muted md:table-cell">{{ $request->channel->label() }}</x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$request->status->tone()" dot>{{ $request->status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="hidden text-muted lg:table-cell">{{ $request->created_at->timezone($tz)->format('j M Y') }}</x-ui.td>
                            <x-ui.td align="right">
                                @if ($request->isOverdue()) <x-ui.badge tone="danger" size="sm">{{ __('privacy.overdue') }}</x-ui.badge>
                                @elseif ($request->isOpen()) <span class="text-xs text-muted">{{ $request->due_at->diffForHumans() }}</span>
                                @else <span class="text-xs text-subtle">—</span> @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
            <div class="border-t border-line">{{ $requests->links() }}</div>
        </x-ui.card>
    @elseif ($tab === 'suppressions')
        <div class="grid gap-6 xl:grid-cols-2 [&>*]:min-w-0">
            <x-ui.card :title="__('privacy.suppression_search')" :description="__('privacy.suppression_search_help')">
                <form wire:submit="lookup" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.phone-input wire:model="lookupPhone" name="lookupPhone" :hint="false" class="flex-1" />
                    <x-ui.button type="submit" variant="secondary" icon="search" loading="lookup">{{ __('ui.search') }}</x-ui.button>
                </form>
                @if ($lookupHash)
                    <div class="mt-5 space-y-3">
                        @php($active = $lookupResults->whereNull('lifted_at'))
                        <x-ui.alert :tone="$active->isNotEmpty() ? 'warning' : 'success'">{{ $active->isNotEmpty() ? __('privacy.suppressed') : __('privacy.not_suppressed') }}</x-ui.alert>
                        @foreach ($lookupResults as $s)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm">
                                <span>{{ $s->channel?->label() ?? __('privacy.all_channels') }} · {{ $s->reason->label() }} · {{ $s->created_at->timezone($tz)->format('j M Y') }}</span>
                                @if ($s->lifted_at) <x-ui.badge size="sm">{{ __('privacy.lifted') }}</x-ui.badge> @endif
                            </div>
                        @endforeach
                        @can('privacy.manage')
                            <div class="flex flex-wrap items-end gap-3 border-t border-line pt-4">
                                <x-ui.select wire:model="suppressChannel" :label="__('supporters.channel')" :options="$channels" class="w-48" />
                                <x-ui.button variant="danger" icon="lock-keyhole" wire:click="suppress" wire:confirm="{{ __('privacy.add_suppression') }}?">{{ __('privacy.add_suppression') }}</x-ui.button>
                            </div>
                        @endcan
                    </div>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('privacy.suppressions')" :description="trans_choice('privacy.active_count', $activeSuppressions, ['count' => number_format($activeSuppressions)])" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($recentSuppressions as $s)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="sp-{{ $s->id }}" x-data="{ lifting: false }">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-fg">{{ $s->reason->label() }} · {{ $s->channel?->label() ?? __('privacy.all_channels') }}</p>
                                <p class="font-mono text-xs text-subtle">{{ substr($s->phone_hash, 0, 12) }}… · {{ $s->source->label() }} · {{ $s->created_at->timezone($tz)->format('j M Y') }}</p>
                            </div>
                            @if (! in_array($s->reason->value, ['opt_out', 'erasure'], true))
                                @can('privacy.manage')
                                    <x-ui.button variant="ghost" size="sm" x-on:click="lifting = !lifting">{{ __('privacy.lift') }}</x-ui.button>
                                    <div x-show="lifting" x-cloak class="flex items-center gap-2">
                                        <input wire:model="liftReason" placeholder="{{ __('privacy.lift_reason') }}" aria-label="{{ __('privacy.lift_reason') }}" class="form-control h-8 min-h-8 w-44">
                                        <x-ui.button size="sm" wire:click="lift({{ $s->id }})">{{ __('ui.confirm') }}</x-ui.button>
                                    </div>
                                @endcan
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="lock-keyhole" :title="__('privacy.not_suppressed')" /></li>
                    @endforelse
                </ul>
                @error('liftReason') <p class="px-5 pb-3 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </x-ui.card>
        </div>
    @elseif ($tab === 'notices')
        <x-ui.card :title="__('privacy.notices')" :padding="false">
            <x-slot:actions>
                @can('privacy.manage')
                    <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'new-notice')">{{ __('privacy.new_notice') }}</x-ui.button>
                @endcan
            </x-slot:actions>
            <ul class="divide-y divide-line">
                @forelse ($notices as $notice)
                    <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start" wire:key="n-{{ $notice->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-sm font-semibold text-fg">{{ $notice->version }} <span class="font-sans font-normal text-muted">· {{ $notice->title }}</span></p>
                            <p class="mt-1 text-sm text-muted">{{ \Illuminate\Support\Str::limit($notice->summary_en, 220) }}</p>
                        </div>
                        @if ($currentNotice?->is($notice))
                            <x-ui.badge tone="success" dot>{{ __('privacy.current') }}</x-ui.badge>
                        @elseif ($notice->published_at)
                            <x-ui.badge>{{ __('privacy.published', ['date' => $notice->published_at->timezone($tz)->format('j M Y')]) }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="warning">{{ __('privacy.draft') }}</x-ui.badge>
                            @can('privacy.manage')
                                <x-ui.button size="sm" wire:click="publishNotice({{ $notice->id }})" wire:confirm="{{ __('privacy.publish_confirm', ['version' => $notice->version]) }}">{{ __('privacy.publish') }}</x-ui.button>
                            @endcan
                        @endif
                    </li>
                @empty
                    <li><x-ui.empty-state icon="file-text" :title="__('supporters.no_notice')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    @else
        <div class="grid gap-6 xl:grid-cols-2 [&>*]:min-w-0">
            <x-ui.card :title="__('privacy.retention')" :description="__('privacy.retention_help')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('supporters.retention') }}</dt><dd class="font-medium text-fg">{{ trans_choice('privacy.months', $retentionMonths, ['count' => $retentionMonths]) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('privacy.legal_holds') }}</dt><dd class="font-medium text-fg tabular">{{ number_format($legalHolds) }}</dd></div>
                    <div class="flex items-center justify-between"><dt class="text-muted">{{ __('privacy.retention_due') }}</dt>
                        <dd>@if ($retentionPreview === null) <x-ui.button variant="ghost" size="sm" wire:click="previewRetention" loading="previewRetention">{{ __('privacy.run_preview') }}</x-ui.button> @else <span class="font-medium tabular">{{ number_format($retentionPreview) }}</span> @endif</dd></div>
                </dl>
            </x-ui.card>
        </div>
    @endif

    @can('dsr.manage')
        <x-ui.modal name="record-request" :title="__('privacy.record_request')" :description="__('privacy.record_help')">
            <form wire:submit="recordRequest" id="dsr-form" class="space-y-4">
                <x-ui.select wire:model="dsrType" :label="__('privacy.type')" :options="$types" required />
                <x-ui.phone-input wire:model="dsrPhone" name="dsrPhone" :label="__('privacy.phone')" />
                <x-ui.textarea wire:model="dsrDetails" :label="__('privacy.details')" rows="3" optional />
                <x-ui.checkbox wire:model="dsrIdentityChecked" :label="__('privacy.identity_checked')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="dsr-form" loading="recordRequest">{{ __('privacy.record_request') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan

    @can('privacy.manage')
        <x-ui.modal name="new-notice" :title="__('privacy.new_notice')" max-width="2xl">
            <form wire:submit="saveNotice" id="notice-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="noticeVersion" :label="__('privacy.version')" placeholder="2026-11-v1" required />
                    <x-ui.input wire:model="noticeTitle" :label="__('privacy.title_label')" required />
                </div>
                <x-ui.textarea wire:model="summaryEn" :label="__('privacy.summary_en')" rows="3" required />
                <x-ui.textarea wire:model="summaryPcm" :label="__('privacy.summary_pcm')" rows="3" optional />
                <x-ui.textarea wire:model="bodyEn" :label="__('privacy.body_en')" rows="6" required />
                <x-ui.textarea wire:model="bodyPcm" :label="__('privacy.body_pcm')" rows="6" optional />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="notice-form" loading="saveNotice">{{ __('privacy.notice_saved') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
