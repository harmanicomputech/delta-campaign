@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="$request->type->label()" :eyebrow="$request->reference">
        <x-slot:actions>
            <x-ui.badge :tone="$request->status->tone()" dot>{{ $request->status->label() }}</x-ui.badge>
            @if ($request->isOverdue()) <x-ui.badge tone="danger">{{ __('privacy.overdue') }}</x-ui.badge> @endif
            @if ($request->isOpen())
                @if (! $request->identity_verified_at)
                    <x-ui.button variant="secondary" icon="shield-check" x-on:click="$dispatch('open-modal', 'verify-identity')">{{ __('privacy.verify_identity') }}</x-ui.button>
                @endif
                <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'reject-request')">{{ __('privacy.reject') }}</x-ui.button>
                <x-ui.button icon="check" :disabled="! $request->identity_verified_at" x-on:click="$dispatch('open-modal', 'complete-request')">{{ __('privacy.complete') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($stopsProcessing)
        <x-ui.alert tone="info" class="mb-6">{{ __('privacy.immediate_effect') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('privacy.details')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ __('privacy.phone') }}</dt><dd class="mt-0.5 font-mono text-fg">{{ $request->maskedPhone() }}</dd></div>
                    <div><dt class="text-muted">{{ __('privacy.channel') }}</dt><dd class="mt-0.5 text-fg">{{ $request->channel->label() }}</dd></div>
                    <div><dt class="text-muted">{{ __('privacy.received') }}</dt><dd class="mt-0.5 text-fg">{{ $request->created_at->timezone($tz)->format('j M Y, H:i') }}</dd></div>
                    <div><dt class="text-muted">{{ __('privacy.due', ['date' => '']) }}</dt><dd class="mt-0.5 text-fg">{{ $request->due_at->timezone($tz)->format('j M Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">{{ __('privacy.identity_verified') }}</dt><dd class="mt-0.5 text-fg">{{ $request->identity_verified_at ? $request->identity_verified_at->timezone($tz)->format('j M Y, H:i').' · '.$request->verification_method : '—' }}</dd></div>
                    @if ($request->details) <div class="sm:col-span-2"><dt class="text-muted">{{ __('privacy.details') }}</dt><dd class="mt-0.5 whitespace-pre-line text-fg">{{ $request->details }}</dd></div> @endif
                    @if ($request->resolution) <div class="sm:col-span-2"><dt class="text-muted">{{ __('privacy.resolution') }}</dt><dd class="mt-0.5 whitespace-pre-line text-fg">{{ $request->resolution }}</dd></div> @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('privacy.linked_record')">
                @if ($request->supporter)
                    <a href="{{ route('workspace.supporters.show', $request->supporter) }}" class="flex items-center gap-3 rounded-lg border border-line p-3 hover:bg-surface-hover">
                        <x-ui.avatar :name="$request->supporter->full_name" size="sm" class="ring-0" />
                        <span class="flex-1"><span class="block text-sm font-medium text-fg">{{ $request->supporter->full_name }}</span><span class="font-mono text-xs text-subtle">{{ $request->supporter->reference }}</span></span>
                        <x-icon name="arrow-right" class="text-subtle" />
                    </a>
                @else
                    <p class="text-sm text-muted">{{ __('privacy.no_record') }}</p>
                @endif
                @if ($isErasure && $request->isOpen()) <x-ui.alert tone="warning" class="mt-4">{{ __('privacy.erasure_note') }}</x-ui.alert> @endif
                @if ($isAccess && $request->identity_verified_at)
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" size="sm" icon="file-text" wire:click="preparePackage" loading="preparePackage">{{ __('privacy.prepare_package') }}</x-ui.button>
                        @if ($request->export_path) <x-ui.button variant="secondary" size="sm" icon="download" wire:click="downloadPackage">{{ __('privacy.download_package') }}</x-ui.button> @endif
                    </div>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card :title="__('privacy.history')" :padding="false">
            <ol class="divide-y divide-line">
                @foreach ($request->transitions as $t)
                    <li class="px-5 py-3">
                        <p class="text-sm font-medium text-fg">{{ \App\Enums\DsrStatus::tryFrom($t->to_status)?->label() ?? $t->to_status }}</p>
                        <p class="text-xs text-subtle">{{ $t->user?->name ?? 'System' }} · {{ $t->created_at->timezone($tz)->format('j M Y, H:i') }}</p>
                        @if ($t->note) <p class="mt-1 text-xs text-muted">{{ $t->note }}</p> @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>

    <x-ui.modal name="verify-identity" :title="__('privacy.verify_identity')">
        <x-ui.input wire:model="verificationMethod" :label="__('privacy.verification_method')" placeholder="e.g. call-back to registered number" required />
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button><x-ui.button wire:click="verifyIdentity" loading="verifyIdentity">{{ __('ui.confirm') }}</x-ui.button></x-slot:footer>
    </x-ui.modal>
    <x-ui.modal name="complete-request" :title="__('privacy.complete')" :description="__('privacy.complete_help')" :icon="$isErasure ? 'triangle-alert' : 'check'" :tone="$isErasure ? 'danger' : 'brand'">
        <x-ui.textarea wire:model="resolution" :label="__('privacy.resolution')" rows="4" required />
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button><x-ui.button :variant="$isErasure ? 'danger' : 'primary'" wire:click="complete" loading="complete">{{ __('privacy.complete') }}</x-ui.button></x-slot:footer>
    </x-ui.modal>
    <x-ui.modal name="reject-request" :title="__('privacy.reject')" :description="__('privacy.reject_help')">
        <x-ui.textarea wire:model="rejection" :label="__('privacy.reason')" rows="3" required />
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button><x-ui.button variant="danger" wire:click="reject" loading="reject">{{ __('privacy.reject') }}</x-ui.button></x-slot:footer>
    </x-ui.modal>
</div>
