@php($tz = config('campaign.display_timezone'))
@php($status = $volunteer->status->value)
<div>
    <x-ui.page-header :title="$volunteer->full_name" :eyebrow="__('volunteers.volunteer')">
        <x-slot:actions>
            <x-ui.badge :tone="$volunteer->status->tone()" dot>{{ $volunteer->status->label() }}</x-ui.badge>
            @if ($volunteer->is_sample) <x-ui.sample-badge /> @endif
            @can('volunteers.approve')
                @if (in_array($status, ['applied', 'rejected'], true) && $volunteer->ward_id)
                    <x-ui.button icon="check" wire:click="approve" loading="approve">{{ __('volunteers.approve') }}</x-ui.button>
                @endif
                @if ($status === 'applied')
                    <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'volunteer-reject')">{{ __('volunteers.reject') }}</x-ui.button>
                @endif
            @endcan
            @can('volunteers.manage')
                @if ($status === 'approved')
                    <x-ui.button variant="secondary" icon="map-pinned" x-on:click="$dispatch('open-modal', 'volunteer-reassign')">{{ __('volunteers.reassign') }}</x-ui.button>
                    <x-ui.button variant="ghost" icon="ban" x-on:click="$dispatch('open-modal', 'volunteer-suspend')">{{ __('volunteers.suspend') }}</x-ui.button>
                @elseif ($status === 'suspended')
                    <x-ui.button variant="secondary" icon="rotate-ccw" wire:click="reactivate" loading="reactivate">{{ __('volunteers.reactivate') }}</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($status === 'applied' && ! $volunteer->ward_id)
        <x-ui.alert tone="warning" class="mb-6">{{ __('volunteers.ward_required') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('volunteers.details')">
                <x-slot:actions>
                    @can('volunteers.manage')
                        <x-ui.button variant="ghost" size="sm" icon="pencil" x-on:click="$dispatch('open-modal', 'volunteer-profile')">{{ __('volunteers.edit_profile') }}</x-ui.button>
                    @endcan
                </x-slot:actions>
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ __('supporters.phone') }}</dt><dd class="mt-0.5 font-mono text-fg">{{ $volunteer->maskedPhone() }}</dd></div>
                    <div><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="mt-0.5 text-fg">{{ collect([$volunteer->ward?->name, $volunteer->lga?->name])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('volunteers.applied') }}</dt><dd class="mt-0.5 text-fg">{{ $volunteer->applied_at->timezone($tz)->format('j M Y, H:i') }} <span class="text-subtle">· {{ __('volunteers.source_'.$volunteer->source) }}</span></dd></div>
                    <div><dt class="text-muted">{{ __('volunteers.account') }}</dt><dd class="mt-0.5 text-fg">
                        @if ($volunteer->user)
                            @can('users.view') <a class="text-primary hover:underline" href="{{ route('workspace.users.show', $volunteer->user) }}">{{ $volunteer->user->name }}</a> @else {{ $volunteer->user->name }} @endcan
                        @else
                            <span class="text-subtle">{{ __('volunteers.no_account') }}</span>
                        @endif
                    </dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">{{ __('volunteers.skills') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($volunteer->skills as $s) <x-ui.badge>{{ $skillOptions[$s] ?? $s }}</x-ui.badge> @empty <span class="text-subtle">—</span> @endforelse
                        </dd>
                    </div>
                    <div class="sm:col-span-2"><dt class="text-muted">{{ __('volunteers.availability') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($volunteer->availability as $a) <x-ui.badge tone="info">{{ $availabilityOptions[$a] ?? $a }}</x-ui.badge> @empty <span class="text-subtle">—</span> @endforelse
                        </dd>
                    </div>
                    @if ($volunteer->decision_reason)
                        <div class="sm:col-span-2"><dt class="text-muted">{{ __('volunteers.reason') }}</dt><dd class="mt-0.5 text-fg">{{ $volunteer->decision_reason }} <span class="text-subtle">· {{ $volunteer->decider?->name }}</span></dd></div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($stats)
                <div class="grid gap-4 sm:grid-cols-3 [&>*]:min-w-0">
                    <x-ui.stat :label="__('volunteers.open_tasks')" :value="number_format($stats['open_tasks'])" icon="list-checks" />
                    <x-ui.stat :label="__('volunteers.completed_tasks')" :value="number_format($stats['completed_tasks'])" icon="circle-check" />
                    <x-ui.stat :label="__('volunteers.contacts_recorded')" :value="number_format($stats['contacts'])" icon="door-open" />
                </div>
            @endif

            <x-ui.card :title="__('volunteers.training')" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($modules as $module)
                        @php($result = $results->get($module->id))
                        <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="m-{{ $module->id }}">
                            <span class="min-w-0 truncate text-sm text-fg">{{ $module->title }}</span>
                            @if ($result)
                                <x-ui.badge :tone="$result->passed ? 'success' : 'warning'" size="sm">{{ $result->passed ? __('training.passed') : __('training.not_passed') }} · {{ $result->score }}%</x-ui.badge>
                            @else
                                <x-ui.badge size="sm">{{ __('training.not_started') }}</x-ui.badge>
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="graduation-cap" :title="__('training.none')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <x-ui.card :title="__('volunteers.history')" :padding="false">
            <ol class="divide-y divide-line">
                @foreach ($history as $step)
                    <li class="px-5 py-3 text-sm" wire:key="h-{{ $step->id }}">
                        <div class="flex items-center gap-2">
                            <x-ui.badge size="sm" :tone="\App\Enums\VolunteerStatus::tryFrom($step->to_status)?->tone() ?? 'neutral'">{{ \App\Enums\VolunteerStatus::tryFrom($step->to_status)?->label() ?? $step->to_status }}</x-ui.badge>
                            <span class="text-xs text-subtle">{{ $step->created_at->timezone($tz)->format('j M Y, H:i') }}</span>
                        </div>
                        <p class="mt-1 text-muted">{{ $step->user?->name ?? __('volunteers.system') }}@if ($step->note) · {{ $step->note }}@endif</p>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>

    @foreach (['reject' => ['ban', 'danger'], 'suspend' => ['ban', 'warning']] as $action => [$icon, $tone])
        <x-ui.modal :name="'volunteer-'.$action" :title="__('volunteers.'.$action.'_title')" :description="__('volunteers.'.$action.'_help')" :icon="$icon" :tone="$tone">
            <form wire:submit="decide('{{ $action }}')" id="volunteer-{{ $action }}-form">
                <x-ui.textarea wire:model="reason" :label="__('volunteers.reason')" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" :form="'volunteer-'.$action.'-form'" variant="danger" loading="decide">{{ __('volunteers.'.$action) }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endforeach

    @can('volunteers.manage')
        <x-ui.modal name="volunteer-reassign" :title="__('volunteers.reassign')" :description="__('volunteers.reassign_help')" icon="map-pinned">
            <form wire:submit="reassign" id="volunteer-reassign-form" class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="__('users.choose')" :options="$lgas->all()" required />
                <x-ui.select wire:model="wardId" :label="__('volunteers.ward')" :placeholder="__('users.choose')" :options="$wards->all()" :disabled="$wards->isEmpty()" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="volunteer-reassign-form" loading="reassign">{{ __('volunteers.reassign') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="volunteer-profile" :title="__('volunteers.edit_profile')" max-width="xl" icon="pencil">
            <form wire:submit="saveProfile" id="volunteer-profile-form" class="space-y-4">
                <fieldset>
                    <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.skills') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($skillOptions as $value => $label) <x-ui.checkbox wire:model="skills" value="{{ $value }}" :label="$label" /> @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-2 text-[0.8125rem] font-semibold text-fg">{{ __('volunteers.availability') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($availabilityOptions as $value => $label) <x-ui.checkbox wire:model="availability" value="{{ $value }}" :label="$label" /> @endforeach
                    </div>
                </fieldset>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="volunteer-profile-form" loading="saveProfile">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
