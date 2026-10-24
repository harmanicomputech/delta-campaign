<div>
    <x-ui.page-header :title="__('account.title')" :description="__('account.intro')" />

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            <x-ui.card :title="__('account.profile')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ __('account.name') }}</dt><dd class="mt-0.5 font-medium text-fg">{{ $user->name }}</dd></div>
                    <div><dt class="text-muted">{{ __('auth.phone') }}</dt><dd class="mt-0.5 font-mono text-fg">{{ $user->maskedPhone() }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">{{ __('account.roles') }}</dt>
                        <dd class="mt-1.5 flex flex-wrap gap-1.5">
                            @forelse ($user->loadedAssignments() as $assignment)
                                <x-ui.badge tone="brand">{{ $assignment->role->name }} · {{ $assignment->scopeLabel() }}</x-ui.badge>
                            @empty
                                <span class="text-subtle">{{ __('shell.no_role') }}</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-subtle">{{ __('account.phone_change_help') }}</p>
            </x-ui.card>

            <x-ui.card id="pin" :title="__('account.pin_title')" :description="__('account.pin_help')">
                <x-slot:actions>
                    @if ($user->pin_hash) <x-ui.badge tone="success" dot>{{ __('account.pin_set_on', ['date' => $user->pin_set_at?->timezone(config('campaign.display_timezone'))->format('j M Y')]) }}</x-ui.badge> @else <x-ui.badge tone="warning" dot>{{ __('account.not_set') }}</x-ui.badge> @endif
                </x-slot:actions>
                <form wire:submit="savePin" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                    <x-ui.input wire:model="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" :label="__('account.new_pin')" />
                    <x-ui.input wire:model="pinConfirmation" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" :label="__('account.confirm_pin')" />
                    <x-ui.button type="submit" class="sm:mt-[1.6rem]" loading="savePin">{{ $user->pin_hash ? __('account.change') : __('account.save') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('account.two_factor')" :description="__('account.two_factor_help')">
                <x-slot:actions>
                    @if ($user->hasTwoFactorEnabled()) <x-ui.badge tone="success" icon="shield-check">{{ __('account.enabled') }}</x-ui.badge> @else <x-ui.badge>{{ __('account.not_enabled') }}</x-ui.badge> @endif
                </x-slot:actions>
                @if ($recoveryCodes)
                    <x-ui.alert tone="warning" :title="__('auth.recovery_title')" class="mb-4">{{ __('auth.recovery_intro') }}</x-ui.alert>
                    <ul class="mb-4 grid grid-cols-2 gap-2 font-mono text-sm">
                        @foreach ($recoveryCodes as $code) <li class="rounded-md border border-line bg-surface-sunken px-3 py-2 text-center select-all">{{ $code }}</li> @endforeach
                    </ul>
                @endif
                @if ($user->hasTwoFactorEnabled())
                    <p class="text-sm text-muted">{{ trans_choice('account.recovery_remaining', count($user->two_factor_recovery_codes ?? []), ['count' => count($user->two_factor_recovery_codes ?? [])]) }}</p>
                    <x-ui.button variant="secondary" size="sm" class="mt-4" icon="refresh-cw" x-on:click="$dispatch('open-modal', 'regenerate-codes')">{{ __('account.regenerate') }}</x-ui.button>
                @else
                    <x-ui.button variant="secondary" size="sm" icon="shield-check" :href="route('two-factor.setup')">{{ __('account.enable') }}</x-ui.button>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('account.sessions')" :description="__('account.sessions_help')" :padding="false">
                <x-slot:actions>
                    @if (count($this->sessions) > 1)
                        <x-ui.button variant="secondary" size="sm" x-on:click="$dispatch('open-modal', 'end-sessions')">{{ __('account.end_others') }}</x-ui.button>
                    @endif
                </x-slot:actions>
                <ul class="divide-y divide-line">
                    @foreach ($this->sessions as $session)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="s-{{ $session->id }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-sunken text-muted"><x-icon name="monitor" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-fg">{{ $deviceLabels[$session->device_id] ?? (new \App\Models\Device(['user_agent' => $session->user_agent]))->label() }}</p>
                                <p class="truncate text-xs text-subtle">{{ $session->ip_address }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p>
                            </div>
                            @if ($session->id === $currentSession)
                                <x-ui.badge tone="success" size="sm">{{ __('account.this_session') }}</x-ui.badge>
                            @else
                                <x-ui.button variant="ghost" size="sm" wire:click="revokeSession('{{ $session->id }}')" wire:confirm="{{ __('account.end_session_confirm') }}">{{ __('account.end') }}</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('account.devices')" :description="__('account.devices_help')" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($this->devices as $device)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="d-{{ $device->id }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-sunken text-muted"><x-icon name="smartphone" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-fg">{{ $device->label() }}</p>
                                <p class="truncate text-xs text-subtle">{{ __('account.first_seen', ['date' => $device->first_seen_at->timezone(config('campaign.display_timezone'))->format('j M Y')]) }} · {{ $device->last_seen_at->diffForHumans() }}</p>
                            </div>
                            @if ($device->id === $currentDevice)
                                <x-ui.badge tone="success" size="sm">{{ __('account.this_device') }}</x-ui.badge>
                            @else
                                <x-ui.button variant="ghost" size="sm" wire:click="revokeDevice('{{ $device->id }}')" wire:confirm="{{ __('account.remove_device_confirm') }}">{{ __('account.remove') }}</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('account.history')" :padding="false">
                <x-ui.table :caption="__('account.history')" dense>
                    <x-slot:head>
                        <tr><x-ui.th>{{ __('account.event') }}</x-ui.th><x-ui.th class="hidden sm:table-cell">{{ __('account.where') }}</x-ui.th><x-ui.th align="right">{{ __('account.when') }}</x-ui.th></tr>
                    </x-slot:head>
                    @foreach ($history as $event)
                        <tr wire:key="h-{{ $event->id }}">
                            <x-ui.td><x-ui.badge :tone="$event->event->tone()" size="sm" dot>{{ $event->event->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="hidden text-xs text-muted sm:table-cell">{{ $event->device?->label() ?? '—' }} · {{ $event->ip }}</x-ui.td>
                            <x-ui.td align="right" class="text-xs text-muted">{{ $event->created_at->timezone(config('campaign.display_timezone'))->format('j M, H:i') }}</x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    <x-ui.confirm-dialog name="end-sessions" :title="__('account.end_others')" :description="__('account.end_others_help')" tone="warning">
        <x-slot:confirm><x-ui.button variant="danger" x-on:click="open = false; $wire.revokeOtherSessions()">{{ __('account.end_others') }}</x-ui.button></x-slot:confirm>
    </x-ui.confirm-dialog>
    <x-ui.confirm-dialog name="regenerate-codes" :title="__('account.regenerate')" :description="__('account.regenerate_help')" tone="warning">
        <x-slot:confirm><x-ui.button x-on:click="open = false; $wire.regenerateRecoveryCodes()">{{ __('account.regenerate') }}</x-ui.button></x-slot:confirm>
    </x-ui.confirm-dialog>
</div>
