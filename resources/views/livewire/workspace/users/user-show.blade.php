<div>
    <x-ui.page-header :title="$user->name" :eyebrow="__('users.account')">
        <x-slot:actions>
            <x-ui.badge :tone="$user->status->tone()" dot>{{ $user->status->label() }}</x-ui.badge>
            @if ($canManage && ! $isSelf)
                <x-ui.dropdown width="w-60" :label="__('ui.actions')">
                    <x-slot:trigger class="h-10 border border-line-strong bg-surface px-4 text-sm font-medium text-fg shadow-xs hover:bg-surface-hover">
                        {{ __('ui.actions') }} <x-icon name="chevron-down" class="text-subtle" />
                    </x-slot:trigger>
                    <x-ui.dropdown-item icon="phone" x-on:click="open = false; $dispatch('open-modal', 'change-phone')">{{ __('users.change_phone') }}</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="log-out" x-on:click="open = false; $dispatch('open-modal', 'sign-out-user')">{{ __('users.sign_out_everywhere') }}</x-ui.dropdown-item>
                    <div class="my-1 border-t border-line"></div>
                    @if ($user->isActive())
                        <x-ui.dropdown-item icon="lock-keyhole" danger x-on:click="open = false; $dispatch('open-modal', 'suspend-user')">{{ __('users.suspend') }}</x-ui.dropdown-item>
                    @else
                        <x-ui.dropdown-item icon="circle-check" x-on:click="open = false; $dispatch('open-modal', 'reactivate-user')">{{ __('users.reactivate') }}</x-ui.dropdown-item>
                    @endif
                </x-ui.dropdown>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($user->isActive())
        <x-ui.alert tone="danger" :title="__('users.suspended_title')" class="mb-6">
            {{ $user->suspension_reason }} · {{ $user->suspended_at?->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }}
        </x-ui.alert>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <x-ui.card :title="__('users.roles')" :description="__('users.roles_help')" :padding="false">
                <x-slot:actions>
                    @if ($canManage && $assignable->isNotEmpty())
                        <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'assign-role')">{{ __('users.assign_role') }}</x-ui.button>
                    @endif
                </x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($assignments as $assignment)
                        <li class="flex items-center gap-4 px-5 py-3.5" wire:key="a-{{ $assignment->id }}">
                            <span @class(['flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-accent-100 text-accent-700 dark:bg-accent-400/12 dark:text-accent-300' => $assignment->role->is_privileged, 'bg-primary-soft text-primary' => ! $assignment->role->is_privileged])>
                                <x-icon :name="$assignment->role->is_privileged ? 'shield-check' : 'key-round'" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-fg">{{ $assignment->role->name }}</p>
                                <p class="text-xs text-subtle">{{ $assignment->scopeLabel() }} · {{ __('users.assigned_by', ['name' => $assignment->assigner?->name ?? __('users.system'), 'date' => $assignment->created_at->timezone(config('campaign.display_timezone'))->format('j M Y')]) }}</p>
                            </div>
                            @if ($canManage && $canRevoke($assignment))
                                <x-ui.button variant="ghost" size="sm" wire:click="revokeAssignment({{ $assignment->id }})" wire:confirm="{{ __('users.revoke_confirm', ['role' => $assignment->role->name]) }}">{{ __('users.revoke') }}</x-ui.button>
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="key-round" :title="__('users.no_roles')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>

            @if ($audit->isNotEmpty())
                <x-ui.card :title="__('users.audit_trail')" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($audit as $entry)
                            <li class="flex items-start justify-between gap-4 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-[0.8125rem] text-fg">{{ $entry->action }}</p>
                                    <p class="text-xs text-subtle">{{ $entry->actor?->name ?? $entry->actor_label }} · {{ $entry->occurred_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }}</p>
                                </div>
                                <span class="font-mono text-[0.6875rem] text-subtle">#{{ $entry->id }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('users.details')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('auth.phone') }}</dt><dd class="font-mono text-fg">{{ $user->maskedPhone() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('account.two_factor') }}</dt><dd>@if ($user->hasTwoFactorEnabled()) <x-ui.badge tone="success" size="sm">{{ __('account.enabled') }}</x-ui.badge> @else <x-ui.badge size="sm">{{ __('account.not_enabled') }}</x-ui.badge> @endif</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('account.pin_title') }}</dt><dd class="text-fg">{{ $user->pin_hash ? __('account.pin_set_on', ['date' => $user->pin_set_at?->format('j M Y')]) : __('account.not_set') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('users.last_sign_in') }}</dt><dd class="text-fg">{{ $user->last_login_at?->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') ?? __('users.never') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('users.created_on') }}</dt><dd class="text-fg">{{ $user->created_at->timezone(config('campaign.display_timezone'))->format('j M Y') }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('account.history')" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($history as $event)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <x-ui.badge :tone="$event->event->tone()" size="sm" dot>{{ $event->event->label() }}</x-ui.badge>
                            <span class="text-xs text-subtle">{{ $event->created_at->timezone(config('campaign.display_timezone'))->format('j M, H:i') }} · {{ $event->ip }}</span>
                        </li>
                    @empty
                        <li><x-ui.empty-state compact icon="clock" :title="__('workspace.no_history')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>

    @if ($canManage)
        <x-ui.modal name="assign-role" :title="__('users.assign_role')" :description="__('users.assign_help')">
            <form wire:submit="assignRole" id="assign-role-form" class="space-y-4">
                <x-ui.select wire:model.live="roleId" :label="__('users.role')" :placeholder="__('users.choose')" :options="$assignable" required />
                <x-workspace.scope-picker :type="$scopeType" :districts="$districts" :lgas="$lgas" :wards="$wards" :lga-id="$lgaId" />
                @error('scope') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
                @error('role') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="assign-role-form" loading="assignRole">{{ __('users.assign_role') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="suspend-user" :title="__('users.suspend_title', ['name' => $user->name])" :description="__('users.suspend_help')" icon="lock-keyhole" tone="danger">
            <x-ui.textarea wire:model="suspensionReason" :label="__('users.reason')" rows="3" required />
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="suspend" loading="suspend">{{ __('users.suspend') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.confirm-dialog name="reactivate-user" :title="__('users.reactivate')" :description="__('users.reactivate_help')" tone="warning">
            <x-slot:confirm><x-ui.button x-on:click="open = false; $wire.reactivate()">{{ __('users.reactivate') }}</x-ui.button></x-slot:confirm>
        </x-ui.confirm-dialog>

        <x-ui.confirm-dialog name="sign-out-user" :title="__('users.sign_out_everywhere')" :description="__('users.sign_out_help')" tone="warning">
            <x-slot:confirm><x-ui.button variant="danger" x-on:click="open = false; $wire.signOutEverywhere()">{{ __('users.sign_out_everywhere') }}</x-ui.button></x-slot:confirm>
        </x-ui.confirm-dialog>

        <x-ui.modal name="change-phone" :title="__('users.change_phone')" :description="__('users.change_phone_help')" icon="phone">
            @if ($phoneChallengeId === null)
                <form wire:submit="sendPhoneCode" id="phone-form" class="space-y-4">
                    <x-ui.phone-input wire:model="newPhone" name="newPhone" :label="__('users.new_number')" />
                </form>
            @else
                <form wire:submit="confirmPhoneChange" id="phone-form" class="space-y-4">
                    <x-ui.alert tone="info">{{ __('users.enter_code_from_new_number') }}</x-ui.alert>
                    <x-ui.input wire:model="phoneCode" :label="__('auth.code')" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required />
                </form>
            @endif
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="phone-form" :loading="$phoneChallengeId ? 'confirmPhoneChange' : 'sendPhoneCode'">{{ $phoneChallengeId ? __('users.confirm_change') : __('auth.send_code') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
