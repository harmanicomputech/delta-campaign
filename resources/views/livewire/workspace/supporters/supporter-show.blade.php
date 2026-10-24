@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="$supporter->full_name" :eyebrow="$supporter->reference">
        <x-slot:actions>
            <x-ui.badge :tone="$supporter->verification_status->tone()" dot>{{ $supporter->verification_status->label() }}</x-ui.badge>
            @if ($supporter->is_sample) <x-ui.sample-badge /> @endif
            @if (! $supporter->isErased())
                @can('supporters.verify')
                    @if ($supporter->verification_status->value !== 'verified')
                        <x-ui.button variant="secondary" icon="check" wire:click="verify" loading="verify">{{ __('supporters.verify') }}</x-ui.button>
                    @endif
                    @if ($supporter->verification_status->value !== 'rejected')
                        <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'reject-supporter')">{{ __('supporters.reject') }}</x-ui.button>
                    @endif
                @endcan
                @can('supporters.update')
                    <x-ui.button icon="pencil" x-on:click="$dispatch('open-modal', 'correct-supporter')">{{ __('supporters.edit') }}</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($supporter->isErased())
        <x-ui.alert tone="neutral" :title="__('supporters.erased')" class="mb-6">{{ __('supporters.erased_banner', ['date' => $supporter->erased_at->timezone($tz)->format('j M Y')]) }}</x-ui.alert>
    @elseif ($supporter->processing_restricted)
        <x-ui.alert tone="warning" :title="__('supporters.restricted')" class="mb-6">{{ __('privacy.immediate_effect') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('supporters.details')">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted">{{ __('supporters.phone') }}</dt>
                        <dd class="mt-0.5 flex items-center gap-2">
                            <span class="font-mono text-fg">{{ $revealedPhone ?? $supporter->maskedPhone() }}</span>
                            @if (! $revealedPhone && ! $supporter->isErased())
                                @can('supporters.view-contact')
                                    <x-ui.button variant="ghost" size="sm" icon="eye" wire:click="reveal" loading="reveal">{{ __('supporters.reveal') }}</x-ui.button>
                                @endcan
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->areaLabel() ?: '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.source') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->source->label() }}@if ($supporter->registeredBy) <span class="text-subtle">· {{ __('supporters.registered_by', ['name' => $supporter->registeredBy->name]) }}</span>@endif</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.registered') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->created_at->timezone($tz)->format('j M Y, H:i') }}</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.language') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->preferred_language === 'pcm' ? 'Naijá (Pidgin)' : 'English' }}</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.volunteer') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->wants_to_volunteer ? __('supporters.volunteer_yes') : '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.retention') }}</dt><dd class="mt-0.5 text-fg">{{ $supporter->retention_until->format('j M Y') }}@if ($supporter->legal_hold) <x-ui.badge tone="warning" size="sm" class="ml-1">{{ __('privacy.legal_holds') }}</x-ui.badge>@endif</dd></div>
                    @if ($supporter->verified_at)
                        <div><dt class="text-muted">{{ __('supporters.status') }}</dt><dd class="mt-0.5 text-fg">{{ __('supporters.verified_by', ['name' => $supporter->verifier?->name ?? '—', 'date' => $supporter->verified_at->timezone($tz)->format('j M Y')]) }}@if ($supporter->rejection_reason)<span class="block text-xs text-danger">{{ $supporter->rejection_reason }}</span>@endif</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('supporters.contacts')" :padding="false">
                <x-slot:actions>
                    @can('supporters.update')
                        @unless ($supporter->isErased())
                            <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'log-contact')">{{ __('supporters.log_contact') }}</x-ui.button>
                        @endunless
                    @endcan
                </x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($contacts as $contact)
                        <li class="px-5 py-3" wire:key="c-{{ $contact->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :tone="$contact->outcome->tone()" size="sm">{{ $contact->outcome->label() }}</x-ui.badge>
                                <span class="text-xs text-muted">{{ $contact->channel->label() }} · {{ $contact->user->name }} · {{ $contact->occurred_at->timezone($tz)->format('j M Y, H:i') }}</span>
                                @if ($contact->follow_up_at) <x-ui.badge tone="brand" size="sm" icon="calendar">{{ $contact->follow_up_at->timezone($tz)->format('j M') }}</x-ui.badge> @endif
                            </div>
                            @if ($contact->notes_encrypted) <p class="mt-1.5 text-sm text-fg">{{ $contact->notes_encrypted }}</p> @endif
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="phone" :title="__('supporters.no_contacts')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>

            @if ($duplicates->isNotEmpty() || auth()->user()->can('supporters.update'))
                <x-ui.card :title="__('supporters.duplicates')" :description="__('supporters.duplicates_help')" :padding="false">
                    <ul class="divide-y divide-line">
                        @forelse ($duplicates as $candidate)
                            <li class="flex items-center gap-4 px-5 py-3" wire:key="d-{{ $candidate->id }}">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('workspace.supporters.show', $candidate) }}" class="font-medium hover:text-primary">{{ $candidate->full_name }}</a>
                                    <p class="font-mono text-xs text-subtle">{{ $candidate->reference }} · {{ $candidate->maskedPhone() }} · {{ (int) round($candidate->name_similarity * 100) }}%</p>
                                </div>
                                <x-ui.button variant="ghost" size="sm" wire:click="merge({{ $candidate->id }})" wire:confirm="{{ __('supporters.merge_confirm', ['other' => $candidate->reference]) }}">{{ __('supporters.merge') }}</x-ui.button>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-subtle">{{ __('supporters.no_duplicates') }}</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('supporters.consents')" :padding="false">
                @if ($consentSummary === [])
                    <p class="px-5 py-4 text-sm text-subtle">—</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach (\App\Enums\ConsentChannel::cases() as $channel)
                            @php($state = $consentSummary[$channel->value]['campaign_updates'] ?? null)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-fg">{{ $channel->label() }}</p>
                                    <p class="text-xs text-subtle">{{ \App\Enums\ConsentPurpose::CampaignUpdates->label() }}</p>
                                </div>
                                @if ($suppressed[$channel->value] ?? false)
                                    <x-ui.badge tone="danger" size="sm" icon="lock-keyhole">{{ __('privacy.suppressed') }}</x-ui.badge>
                                @else
                                    <x-ui.badge :tone="$state?->tone() ?? 'neutral'" size="sm" dot>{{ $state?->label() ?? __('supporters.not_asked') }}</x-ui.badge>
                                @endif
                                @can('supporters.update')
                                    @unless ($supporter->isErased())
                                        @if ($state?->value === 'granted')
                                            <x-ui.button variant="ghost" size="sm" wire:click="setConsent('{{ $channel->value }}', 'withdrawn')" wire:confirm="{{ __('supporters.confirm_withdrawal', ['channel' => $channel->label()]) }}">{{ __('supporters.record_withdrawal') }}</x-ui.button>
                                        @elseif (! ($suppressed[$channel->value] ?? false) || $state?->value === 'withdrawn')
                                            <x-ui.button variant="ghost" size="sm" wire:click="setConsent('{{ $channel->value }}', 'granted')" wire:confirm="{{ __('supporters.confirm_consent', ['channel' => $channel->label()]) }}">{{ __('supporters.record_consent') }}</x-ui.button>
                                        @endif
                                    @endunless
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('supporters.consent_history')" :padding="false">
                <ol class="divide-y divide-line">
                    @forelse ($consentHistory as $record)
                        <li class="px-5 py-2.5 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-fg">{{ $record->channel->label() }} · {{ $record->status->label() }}</span>
                                <span class="text-subtle">{{ $record->recorded_at->timezone($tz)->format('j M Y, H:i') }}</span>
                            </div>
                            <p class="mt-0.5 text-subtle">{{ $record->source->label() }}@if ($record->recorder) · {{ $record->recorder->name }}@endif @if ($record->notice_version) · {{ __('supporters.notice_shown', ['version' => $record->notice_version]) }}@endif</p>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-subtle">—</li>
                    @endforelse
                </ol>
            </x-ui.card>

            @if ($requests->isNotEmpty())
                <x-ui.card :title="__('supporters.requests')" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($requests as $request)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <a href="{{ route('workspace.privacy.requests.show', $request) }}" class="font-mono text-sm hover:text-primary">{{ $request->reference }}</a>
                                <x-ui.badge :tone="$request->status->tone()" size="sm">{{ $request->type->label() }} · {{ $request->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($audit->isNotEmpty())
                <x-ui.card :title="__('users.audit_trail')" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($audit as $entry)
                            <li class="flex items-start justify-between gap-3 px-5 py-2.5">
                                <div class="min-w-0"><p class="font-mono text-[0.8125rem] text-fg">{{ $entry->action }}</p><p class="text-xs text-subtle">{{ $entry->actor?->name ?? $entry->actor_label }} · {{ $entry->occurred_at->timezone($tz)->format('j M Y, H:i') }}</p></div>
                                <span class="font-mono text-[0.6875rem] text-subtle">#{{ $entry->id }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>

    @can('supporters.update')
        <x-ui.modal name="correct-supporter" :title="__('supporters.edit')" :description="__('supporters.edit_help')" max-width="xl">
            <form wire:submit="saveCorrection" id="correct-form" class="space-y-4">
                <x-ui.input wire:model="fullName" :label="__('supporters.full_name')" required />
                <x-ui.phone-input wire:model="phone" name="phone" :required="false" :hint="__('supporters.masked').': '.$supporter->maskedPhone()" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="lgaId" :label="__('supporters.lga')" :options="$lgas" required />
                    <x-ui.select wire:model="wardId" :label="__('supporters.ward')" :placeholder="__('users.choose')" :options="$wards" optional />
                </div>
                <x-ui.select wire:model="language" :label="__('supporters.language')" :options="['en' => 'English', 'pcm' => 'Naijá (Pidgin)']" />
                <x-ui.checkbox wire:model="wantsToVolunteer" :label="__('supporters.volunteer')" />
                <x-ui.textarea wire:model="reason" :label="__('supporters.reason')" rows="2" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="correct-form" loading="saveCorrection">{{ __('supporters.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="log-contact" :title="__('supporters.log_contact')">
            <form wire:submit="logContact" id="contact-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="contactChannel" :label="__('supporters.channel')" :options="$channels" />
                    <x-ui.select wire:model="contactOutcome" :label="__('supporters.outcome')" :options="$outcomes" />
                </div>
                <x-ui.textarea wire:model="contactNotes" :label="__('supporters.notes')" rows="3" optional />
                <x-ui.input wire:model="followUpOn" type="date" :label="__('supporters.follow_up_at')" optional />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="contact-form" loading="logContact">{{ __('supporters.log_contact') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan

    @can('supporters.verify')
        <x-ui.modal name="reject-supporter" :title="__('supporters.reject')" icon="circle-x" tone="danger">
            <x-ui.textarea wire:model="rejectReason" :label="__('supporters.reject_reason')" rows="3" required />
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="reject" loading="reject">{{ __('supporters.reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
