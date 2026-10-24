@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('volunteers.title')" :description="__('volunteers.intro')">
        <x-slot:actions>
            @can('volunteers.manage')
                <x-ui.button icon="user-plus" x-on:click="$dispatch('open-modal', 'add-volunteer')">{{ __('volunteers.add') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 xl:flex-row xl:items-center">
            <div class="inline-flex shrink-0 rounded-lg border border-line bg-surface-sunken p-0.5" role="group" aria-label="{{ __('volunteers.view') }}">
                @foreach (['applications' => [__('volunteers.applications'), $counts->applications], 'active' => [__('volunteers.active'), $counts->active], 'all' => [__('volunteers.all'), $counts->total]] as $key => [$label, $count])
                    <button type="button" wire:click="$set('view', '{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                            @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[0.8125rem] font-medium transition-colors', 'bg-surface text-fg shadow-xs' => $view === $key, 'text-subtle hover:text-fg' => $view !== $key])>
                        {{ $label }} <span class="rounded-full bg-canvas px-1.5 text-[0.6875rem] tabular-nums text-muted">{{ number_format($count) }}</span>
                    </button>
                @endforeach
            </div>
            <div class="relative flex-1 xl:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('volunteers.search') }}" aria-label="{{ __('volunteers.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <select wire:model.live="lga" aria-label="LGA" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_lgas') }}</option>
                    @foreach ($lgas as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
                <select wire:model.live="ward" aria-label="{{ __('volunteers.ward') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8" @disabled($wards->isEmpty())>
                    <option value="">{{ __('supporters.all_wards') }}</option>
                    @foreach ($wards as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
                <select wire:model.live="skill" aria-label="{{ __('volunteers.skills') }}" class="form-control col-span-2 h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('volunteers.all_skills') }}</option>
                    @foreach ($skillOptions as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div wire:loading.flex class="hidden items-center gap-2 text-xs text-subtle xl:ml-auto"><x-ui.spinner class="size-4" /> {{ __('ui.loading') }}</div>
        </div>

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @if ($volunteers->isEmpty())
                <x-ui.empty-state icon="hand-heart" :title="$view === 'applications' ? __('volunteers.no_applications') : __('volunteers.none')" :description="$search || $lga || $skill ? __('supporters.none_filtered') : __('volunteers.none_help')" />
            @else
                <x-ui.table :caption="__('volunteers.title')">
                    <x-slot:head>
                        <tr>
                            <x-ui.th column="full_name" sortable :sort="$sort" :direction="$direction">{{ __('volunteers.name') }}</x-ui.th>
                            <x-ui.th class="hidden md:table-cell">{{ __('supporters.area') }}</x-ui.th>
                            <x-ui.th class="hidden lg:table-cell">{{ __('volunteers.skills') }}</x-ui.th>
                            <x-ui.th>{{ __('supporters.status') }}</x-ui.th>
                            <x-ui.th column="applied_at" sortable :sort="$sort" :direction="$direction" class="hidden xl:table-cell">{{ __('volunteers.applied') }}</x-ui.th>
                            <x-ui.th align="right"><span class="sr-only">{{ __('ui.actions') }}</span></x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($volunteers as $volunteer)
                        <tr wire:key="v-{{ $volunteer->id }}">
                            <x-ui.td>
                                <a href="{{ route('workspace.volunteers.show', $volunteer) }}" class="block font-medium hover:text-primary">{{ $volunteer->full_name }}</a>
                                <p class="font-mono text-xs text-subtle">{{ $volunteer->maskedPhone() }}@if ($volunteer->is_sample) <x-ui.sample-badge class="ml-1" /> @endif</p>
                            </x-ui.td>
                            <x-ui.td class="hidden text-muted md:table-cell">{{ $volunteer->ward?->name ?? '—' }}<span class="block text-xs text-subtle">{{ $volunteer->lga?->name }}</span></x-ui.td>
                            <x-ui.td class="hidden lg:table-cell">
                                <div class="flex max-w-xs flex-wrap gap-1">
                                    @forelse ($volunteer->skills as $s)
                                        <x-ui.badge size="sm">{{ \App\Enums\VolunteerSkill::tryFrom($s)?->label() ?? $s }}</x-ui.badge>
                                    @empty
                                        <span class="text-subtle">—</span>
                                    @endforelse
                                </div>
                            </x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$volunteer->status->tone()" dot>{{ $volunteer->status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="hidden text-muted xl:table-cell">{{ $volunteer->applied_at->timezone($tz)->format('j M Y') }}</x-ui.td>
                            <x-ui.td align="right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($volunteer->status->value === 'applied')
                                        @can('volunteers.approve')
                                            @if ($volunteer->ward_id)
                                                <x-ui.button size="sm" variant="secondary" icon="check" wire:click="approve({{ $volunteer->id }})" loading="approve({{ $volunteer->id }})">{{ __('volunteers.approve') }}</x-ui.button>
                                            @endif
                                            <x-ui.button size="sm" variant="ghost" wire:click="confirmReject({{ $volunteer->id }})">{{ __('volunteers.reject') }}</x-ui.button>
                                        @endcan
                                    @endif
                                    <x-ui.button variant="ghost" size="sm" square icon="arrow-up-right" :href="route('workspace.volunteers.show', $volunteer)"><span class="sr-only">{{ __('ui.view_details') }}: {{ $volunteer->full_name }}</span></x-ui.button>
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>
        <div class="border-t border-line">{{ $volunteers->links() }}</div>
    </x-ui.card>

    @can('volunteers.approve')
        <x-ui.modal name="reject-volunteer" :title="__('volunteers.reject_title')" :description="__('volunteers.reject_help')" icon="ban" tone="danger">
            <form wire:submit="reject" id="reject-volunteer-form">
                <x-ui.textarea wire:model="rejectReason" :label="__('volunteers.reason')" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="reject-volunteer-form" variant="danger" loading="reject">{{ __('volunteers.reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan

    @can('volunteers.manage')
        <x-ui.modal name="add-volunteer" :title="__('volunteers.add')" :description="__('volunteers.add_help')" max-width="xl" icon="user-plus">
            <form wire:submit="addApplication" id="add-volunteer-form" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="fullName" :label="__('volunteers.full_name')" autocomplete="off" required />
                    <x-ui.phone-input wire:model="phone" name="phone" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model.live="formLgaId" label="LGA" :placeholder="__('users.choose')" :options="$formLgas->all()" required />
                    <x-ui.select wire:model="formWardId" :label="__('volunteers.ward')" :placeholder="$formLgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$formWards->all()" :disabled="$formWards->isEmpty()" required />
                </div>
                <fieldset>
                    <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.skills') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($skillOptions as $value => $label)
                            <x-ui.checkbox wire:model="skills" value="{{ $value }}" :label="$label" />
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.availability') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($availabilityOptions as $value => $label)
                            <x-ui.checkbox wire:model="availability" value="{{ $value }}" :label="$label" />
                        @endforeach
                    </div>
                </fieldset>
                <x-ui.alert tone="info">{{ __('volunteers.add_privacy') }}</x-ui.alert>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="add-volunteer-form" loading="addApplication">{{ __('volunteers.add_submit') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
