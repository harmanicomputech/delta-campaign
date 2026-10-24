<div>
    <x-ui.page-header :title="__('users.title')" :description="__('users.intro')">
        <x-slot:actions>
            @can('users.manage')
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'create-user')">{{ __('users.add') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 lg:flex-row lg:items-center">
            <div class="relative flex-1 lg:max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('users.search') }}" aria-label="{{ __('users.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="status" aria-label="{{ __('users.status') }}" class="form-control h-9 min-h-9 w-auto pr-8">
                    <option value="">{{ __('users.all_statuses') }}</option>
                    @foreach ($statuses as $s) <option value="{{ $s->value }}">{{ $s->label() }}</option> @endforeach
                </select>
                <select wire:model.live="role" aria-label="{{ __('users.role') }}" class="form-control h-9 min-h-9 w-auto pr-8">
                    <option value="">{{ __('users.all_roles') }}</option>
                    @foreach ($roles as $slug => $label) <option value="{{ $slug }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div wire:loading.flex class="hidden items-center gap-2 text-xs text-subtle lg:ml-auto"><x-ui.spinner class="size-4" /> {{ __('ui.loading') }}</div>
        </div>

        @if ($users->isEmpty())
            <x-ui.empty-state icon="users" :title="__('users.none')" :description="$search || $status || $role ? __('users.none_filtered') : __('users.none_help')" />
        @else
            <x-ui.table :caption="__('users.title')">
                <x-slot:head>
                    <tr>
                        <x-ui.th column="name" sortable :sort="$sort" :direction="$direction">{{ __('users.name') }}</x-ui.th>
                        <x-ui.th class="hidden md:table-cell">{{ __('users.roles') }}</x-ui.th>
                        <x-ui.th>{{ __('users.status') }}</x-ui.th>
                        <x-ui.th column="last_login_at" sortable :sort="$sort" :direction="$direction" class="hidden lg:table-cell">{{ __('users.last_sign_in') }}</x-ui.th>
                        <x-ui.th align="right"><span class="sr-only">{{ __('ui.actions') }}</span></x-ui.th>
                    </tr>
                </x-slot:head>
                @foreach ($users as $user)
                    <tr wire:key="u-{{ $user->id }}">
                        <x-ui.td>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$user->name" size="sm" class="ring-0" />
                                <div class="min-w-0">
                                    <a href="{{ route('workspace.users.show', $user) }}" class="block truncate font-medium hover:text-primary">{{ $user->name }}</a>
                                    <p class="font-mono text-xs text-subtle">{{ $user->maskedPhone() }}</p>
                                </div>
                            </div>
                        </x-ui.td>
                        <x-ui.td class="hidden md:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->activeAssignments as $assignment)
                                    <x-ui.badge size="sm" :tone="$assignment->role->is_privileged ? 'accent' : 'neutral'">{{ $assignment->role->name }}</x-ui.badge>
                                @empty
                                    <span class="text-xs text-subtle">—</span>
                                @endforelse
                            </div>
                        </x-ui.td>
                        <x-ui.td><x-ui.badge :tone="$user->status->tone()" dot>{{ $user->status->label() }}</x-ui.badge></x-ui.td>
                        <x-ui.td class="hidden text-muted lg:table-cell">{{ $user->last_login_at?->diffForHumans() ?? __('users.never') }}</x-ui.td>
                        <x-ui.td align="right">
                            <x-ui.button variant="ghost" size="sm" square icon="arrow-up-right" :href="route('workspace.users.show', $user)"><span class="sr-only">{{ __('ui.view_details') }}: {{ $user->name }}</span></x-ui.button>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
        <div class="border-t border-line">{{ $users->links() }}</div>
    </x-ui.card>

    @can('users.manage')
        <x-ui.modal name="create-user" :title="__('users.add')" :description="__('users.add_help')" max-width="lg">
            <form wire:submit="create" id="create-user-form" class="space-y-4">
                <x-ui.input wire:model="name" :label="__('users.name')" autocomplete="off" required />
                <x-ui.phone-input wire:model="phone" name="phone" />
                <x-ui.select wire:model.live="roleId" :label="__('users.role')" :placeholder="__('users.choose')" :options="$assignable" required
                             :hint="$assignable->isEmpty() ? __('users.no_assignable') : null" />
                <x-workspace.scope-picker :type="$scopeType" :districts="$districts" :lgas="$lgas" :wards="$wards" :lga-id="$lgaId" />
                @error('scope') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="create-user-form" loading="create">{{ __('users.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
