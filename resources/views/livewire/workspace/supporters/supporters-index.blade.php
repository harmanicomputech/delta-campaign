@php($consentIcon = fn (?string $s) => match ($s) { 'granted' => ['check', 'text-success', __('supporters.granted')], 'withdrawn' => ['x', 'text-subtle', __('supporters.withdrawn')], default => ['ellipsis', 'text-line-strong', __('supporters.not_asked')] })
<div>
    <x-ui.page-header :title="__('supporters.title')" :description="__('supporters.intro')">
        <x-slot:actions>
            @can('supporters.export')
                <x-ui.button variant="secondary" icon="download" x-on:click="$dispatch('open-modal', 'export-supporters')">{{ __('supporters.export') }}</x-ui.button>
            @endcan
            @can('supporters.create')
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'register-supporter')">{{ __('supporters.add') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 xl:flex-row xl:items-center">
            <div class="relative flex-1 xl:max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('supporters.search') }}" aria-label="{{ __('supporters.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <select wire:model.live="lga" aria-label="{{ __('supporters.lga') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_lgas') }}</option>
                    @foreach ($lgas as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
                <select wire:model.live="ward" aria-label="{{ __('supporters.ward') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8" @disabled($wards->isEmpty())>
                    <option value="">{{ __('supporters.all_wards') }}</option>
                    @foreach ($wards as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
                <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_statuses') }}</option>
                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
                <select wire:model.live="source" aria-label="{{ __('supporters.source') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_sources') }}</option>
                    @foreach ($sources as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div wire:loading.flex class="hidden items-center gap-2 text-xs text-subtle xl:ml-auto"><x-ui.spinner class="size-4" /> {{ __('ui.loading') }}</div>
        </div>

        @if ($selected !== [])
            <div class="flex items-center gap-3 border-b border-line bg-primary-soft/60 px-5 py-2.5 text-sm">
                <span class="font-medium text-primary-soft-fg">{{ trans_choice('ui.rows_selected', count($selected), ['count' => count($selected)]) }}</span>
                @can('supporters.verify')
                    <x-ui.button variant="secondary" size="sm" icon="check" x-on:click="$dispatch('open-modal', 'confirm-verify')">{{ __('supporters.verify_selected') }}</x-ui.button>
                @endcan
                <x-ui.button variant="ghost" size="sm" wire:click="$set('selected', [])">{{ __('ui.clear_selection') }}</x-ui.button>
            </div>
        @endif

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @if ($supporters->isEmpty())
                <x-ui.empty-state icon="users" :title="__('supporters.none')" :description="$search || $lga || $status || $source ? __('supporters.none_filtered') : __('supporters.none_help')" />
            @else
                <x-ui.table :caption="__('supporters.title')">
                    <x-slot:head>
                        <tr>
                            @can('supporters.verify') <th scope="col" class="w-10 border-b border-line py-2.5 pl-5"><span class="sr-only">{{ __('ui.actions') }}</span></th> @endcan
                            <x-ui.th column="full_name" sortable :sort="$sort" :direction="$direction">{{ __('supporters.name') }}</x-ui.th>
                            <x-ui.th class="hidden md:table-cell">{{ __('supporters.area') }}</x-ui.th>
                            <x-ui.th>{{ __('supporters.status') }}</x-ui.th>
                            <x-ui.th class="hidden lg:table-cell">{{ __('supporters.consent') }}</x-ui.th>
                            <x-ui.th column="created_at" sortable :sort="$sort" :direction="$direction" class="hidden xl:table-cell">{{ __('supporters.registered') }}</x-ui.th>
                            <x-ui.th align="right"><span class="sr-only">{{ __('ui.actions') }}</span></x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($supporters as $supporter)
                        <tr wire:key="s-{{ $supporter->id }}">
                            @can('supporters.verify')
                                <x-ui.td class="w-10"><input type="checkbox" wire:model.live="selected" value="{{ $supporter->id }}" aria-label="Select {{ $supporter->reference }}" class="size-4 rounded accent-[var(--c-primary)]"></x-ui.td>
                            @endcan
                            <x-ui.td>
                                <a href="{{ route('workspace.supporters.show', $supporter) }}" class="block font-medium hover:text-primary">{{ $supporter->full_name }}</a>
                                <p class="font-mono text-xs text-subtle">{{ $supporter->reference }} · {{ $supporter->maskedPhone() }}</p>
                            </x-ui.td>
                            <x-ui.td class="hidden text-muted md:table-cell">{{ $supporter->ward?->name ?? '—' }}<span class="block text-xs text-subtle">{{ $supporter->lga?->name }}</span></x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$supporter->verification_status->tone()" dot>{{ $supporter->verification_status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="hidden lg:table-cell">
                                <div class="flex items-center gap-3 text-xs text-muted">
                                    @foreach (['SMS' => $supporter->sms_consent, 'WA' => $supporter->whatsapp_consent] as $label => $state)
                                        @php([$icon, $tone, $title] = $consentIcon($state))
                                        <span class="inline-flex items-center gap-1" title="{{ $label }}: {{ $title }}"><x-icon :name="$icon" class="size-3.5 {{ $tone }}" />{{ $label }}</span>
                                    @endforeach
                                </div>
                            </x-ui.td>
                            <x-ui.td class="hidden text-muted xl:table-cell">{{ $supporter->created_at->timezone(config('campaign.display_timezone'))->format('j M Y') }}</x-ui.td>
                            <x-ui.td align="right">
                                <x-ui.button variant="ghost" size="sm" square icon="arrow-up-right" :href="route('workspace.supporters.show', $supporter)"><span class="sr-only">{{ __('ui.view_details') }}: {{ $supporter->reference }}</span></x-ui.button>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>
        <div class="border-t border-line">{{ $supporters->links() }}</div>
    </x-ui.card>

    @can('supporters.verify')
        <x-ui.confirm-dialog name="confirm-verify" :title="__('supporters.verify_selected')" :description="trans_choice('ui.rows_selected', count($selected), ['count' => count($selected)])" tone="warning">
            <x-slot:confirm><x-ui.button x-on:click="open = false; $wire.verifySelected()">{{ __('supporters.verify') }}</x-ui.button></x-slot:confirm>
        </x-ui.confirm-dialog>
    @endcan

    @can('supporters.create')
        <x-ui.modal name="register-supporter" :title="__('supporters.add')" :description="__('supporters.add_help')" max-width="xl">
            <form wire:submit="register" id="register-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="fullName" :label="__('supporters.full_name')" autocomplete="off" required />
                    <x-ui.phone-input wire:model="phone" name="phone" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="formLgaId" :label="__('supporters.lga')" :placeholder="__('users.choose')" :options="$formLgas->all()" required />
                    <x-ui.select wire:model="formWardId" :label="__('supporters.ward')" :placeholder="$formLgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$formWards->all()" :disabled="$formWards->isEmpty()" optional />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="pollingUnitCode" :label="__('supporters.polling_unit_code')" />
                    <x-ui.select wire:model="language" :label="__('supporters.language')" :options="['en' => 'English', 'pcm' => 'Naijá (Pidgin)']" />
                </div>
                <fieldset class="space-y-3 rounded-lg border border-line p-4">
                    <legend class="px-1 text-[0.8125rem] font-semibold text-fg">{{ __('supporters.consent_heading') }}</legend>
                    @if ($notice)
                        <p class="text-xs text-muted">{{ __('supporters.consent_help') }} <span class="text-subtle">{{ __('supporters.notice_shown', ['version' => $notice->version]) }}</span></p>
                        <p class="rounded-md bg-surface-sunken px-3 py-2 text-xs text-muted">{{ $notice->summary() }}</p>
                        <x-ui.checkbox wire:model="consentSms" :label="__('supporters.consent_sms')" />
                        <x-ui.checkbox wire:model="consentWhatsapp" :label="__('supporters.consent_whatsapp')" />
                    @else
                        <x-ui.alert tone="warning">{{ __('supporters.no_notice') }}</x-ui.alert>
                    @endif
                    @error('consentSms') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                </fieldset>
                <x-ui.checkbox wire:model="wantsToVolunteer" :label="__('supporters.volunteer')" />
                <div>
                    <x-ui.checkbox wire:model="adult" :label="__('supporters.adult')" />
                    @error('adult') <p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="register-form" loading="register">{{ __('supporters.register') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan

    @can('supporters.export')
        <x-ui.modal name="export-supporters" :title="__('supporters.export_title')" :description="__('supporters.export_help')" icon="download">
            @can('supporters.view-contact')
                <x-ui.checkbox wire:model="includeContact" :label="__('supporters.include_contact')" :description="__('supporters.include_contact_help')" />
            @endcan
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button wire:click="export" loading="export" icon="download">{{ __('supporters.export') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
