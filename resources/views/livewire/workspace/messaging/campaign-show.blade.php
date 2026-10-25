@php($tz = config('campaign.display_timezone'))
@php($status = $campaign->status->value)
@php($done = ($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['failed'] ?? 0) + ($counts['skipped'] ?? 0) + ($counts['cancelled'] ?? 0))
<div @if ($poll) wire:poll.10s.visible @endif>
    <x-ui.page-header :title="$campaign->name" :eyebrow="__('messaging.campaign')">
        <x-slot:actions>
            <x-ui.badge :tone="$campaign->status->tone()" dot>{{ $campaign->status->label() }}</x-ui.badge>
            @if ($canManage && $campaign->status->isEditable())
                <x-ui.button variant="secondary" icon="pencil" :href="route('workspace.messaging.edit', $campaign)">{{ __('ui.edit') }}</x-ui.button>
                <x-ui.button icon="send" wire:click="submit" loading="submit">{{ __('messaging.submit') }}</x-ui.button>
            @endif
            @if ($status === 'pending_approval' && $canApprove)
                <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'reject-campaign')">{{ __('messaging.reject') }}</x-ui.button>
                <x-ui.button icon="check" x-on:click="$dispatch('open-modal', 'approve-campaign')">{{ __('messaging.approve') }}</x-ui.button>
            @endif
            @if (in_array($status, ['sending', 'scheduled'], true) && ($canManage || $canApprove))
                <x-ui.button variant="secondary" icon="circle-dot" wire:click="pause" loading="pause">{{ __('messaging.pause') }}</x-ui.button>
            @endif
            @if ($status === 'paused' && ($canManage || $canApprove))
                <x-ui.button icon="rotate-ccw" wire:click="resume" loading="resume">{{ __('messaging.resume') }}</x-ui.button>
            @endif
            @if (! $campaign->status->isFinished() && ($canManage || $canApprove))
                <x-ui.button variant="ghost" icon="ban" x-on:click="$dispatch('open-modal', 'cancel-campaign')">{{ __('messaging.cancel') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($status === 'pending_approval')
        <x-ui.alert tone="warning" class="mb-6">{{ $campaign->created_by === auth()->id() ? __('messaging.waiting_other') : ($canApprove ? __('messaging.waiting_you') : __('messaging.waiting_other')) }}</x-ui.alert>
    @elseif ($status === 'rejected')
        <x-ui.alert tone="danger" :title="__('messaging.rejected_title', ['name' => $campaign->approver?->name])" class="mb-6">{{ $campaign->decision_note }}</x-ui.alert>
    @elseif ($status === 'paused')
        <x-ui.alert tone="warning" class="mb-6">{{ $campaign->pause_reason }}</x-ui.alert>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5 [&>*]:min-w-0">
        <x-ui.stat :label="__('messaging.recipients')" :value="number_format($total ?: $campaign->estimated_recipients)" icon="users" :hint="$total ? null : __('messaging.estimated')" />
        <x-ui.stat :label="__('messaging.sent')" :value="number_format(($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0))" icon="send" />
        <x-ui.stat :label="__('messaging.delivered')" :value="number_format($counts['delivered'] ?? 0)" icon="check-check" />
        <x-ui.stat :label="__('messaging.not_sent')" :value="number_format(($counts['skipped'] ?? 0) + ($counts['failed'] ?? 0))" icon="circle-x" :hint="__('messaging.failed_count', ['count' => number_format($counts['failed'] ?? 0)])" />
        <x-ui.stat :label="__('messaging.cost')" :value="'₦'.number_format((float) ($campaign->started_at ? $campaign->actual_cost : $campaign->estimated_cost), 2)" icon="gauge" :hint="$campaign->started_at ? __('messaging.estimate_was', ['amount' => number_format((float) $campaign->estimated_cost, 2)]) : __('messaging.estimated')" class="col-span-2 lg:col-span-1" />
    </div>

    @if ($total > 0)
        <x-ui.card class="mb-6">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-medium text-fg">{{ __('messaging.progress', ['done' => number_format($done), 'total' => number_format($total)]) }}</span>
                @if ($counts['queued'] ?? 0) <span class="text-xs text-muted">{{ __('messaging.queued_left', ['count' => number_format($counts['queued'])]) }}</span> @endif
            </div>
            <x-ui.progress class="mt-3" :value="$done" :max="max(1, $total)" :tone="$status === 'completed' ? 'success' : 'brand'" :label="__('messaging.progress', ['done' => $done, 'total' => $total])" />
            @if ($skips->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($skips as $reason => $n) <x-ui.badge size="sm">{{ $reason }}: {{ number_format($n) }}</x-ui.badge> @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] [&>*]:min-w-0">
        <x-ui.card :title="__('messaging.messages')" :padding="false">
            <x-slot:actions>
                <select wire:model.live="messageStatus" aria-label="{{ __('supporters.status') }}" class="form-control h-8 min-h-8 w-auto py-0 pr-8 text-xs">
                    <option value="">{{ __('supporters.all_statuses') }}</option>
                    @foreach (\App\Enums\MessageStatus::cases() as $s) <option value="{{ $s->value }}">{{ $s->label() }}</option> @endforeach
                </select>
            </x-slot:actions>
            @if ($messages->isEmpty())
                <x-ui.empty-state compact icon="send" :title="$campaign->started_at ? __('messaging.no_messages') : __('messaging.not_started')" />
            @else
                <x-ui.table :caption="__('messaging.messages')">
                    <x-slot:head>
                        <tr>
                            <x-ui.th>{{ __('supporters.phone') }}</x-ui.th>
                            <x-ui.th class="hidden sm:table-cell">LGA</x-ui.th>
                            <x-ui.th>{{ __('supporters.status') }}</x-ui.th>
                            <x-ui.th class="hidden md:table-cell">{{ __('messaging.updated') }}</x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($messages as $message)
                        <tr wire:key="m-{{ $message->id }}">
                            <x-ui.td class="font-mono text-xs">+234 ••• ••• {{ $message->phone_last4 }} <span class="text-subtle">{{ strtoupper($message->language) }}</span></x-ui.td>
                            <x-ui.td class="hidden text-muted sm:table-cell">{{ $message->lga?->name ?? '—' }}</x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :tone="$message->status->tone()" size="sm">{{ $message->status->label() }}</x-ui.badge>
                                @if ($message->skip_reason) <span class="block text-xs text-subtle">{{ $message->skip_reason->label() }}</span> @endif
                                @if ($message->last_error) <span class="block font-mono text-xs text-danger">{{ $message->last_error }}</span> @endif
                            </x-ui.td>
                            <x-ui.td class="hidden text-xs text-muted md:table-cell">{{ $message->updated_at->timezone($tz)->format('j M, H:i') }}</x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div class="border-t border-line">{{ $messages->links() }}</div>
            @endif
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card :title="__('messaging.details')">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.channel') }}</dt><dd class="text-fg">{{ $campaign->channel->label() }} · {{ $campaign->purpose->label() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.template') }}</dt><dd class="text-right text-fg">{{ $campaign->template->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.created_by') }}</dt><dd class="text-right text-fg">{{ $campaign->creator?->name }}</dd></div>
                    @if ($campaign->approved_at)
                        <div class="flex justify-between gap-3"><dt class="text-muted">{{ $status === 'rejected' ? __('messaging.rejected_by') : __('messaging.approved_by') }}</dt><dd class="text-right text-fg">{{ $campaign->approver?->name }} <span class="block text-xs text-subtle">{{ $campaign->approved_at->timezone($tz)->format('j M Y, H:i') }}</span></dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.schedule') }}</dt><dd class="text-right text-fg">{{ $campaign->scheduled_at?->timezone($tz)->format('j M Y, H:i') ?? __('messaging.on_approval') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.approval_rule') }}</dt><dd class="text-right text-fg">{{ $needsSecond ? __('messaging.second_person') : __('messaging.single_person') }}</dd></div>
                </dl>
                <div class="mt-4 rounded-2xl rounded-bl-sm bg-surface-sunken px-4 py-3 text-sm whitespace-pre-line text-fg">{{ $preview }}</div>
            </x-ui.card>

            <x-ui.card :title="__('volunteers.history')" :padding="false">
                <ol class="divide-y divide-line">
                    @foreach ($history as $step)
                        <li class="px-5 py-3 text-sm">
                            <x-ui.badge size="sm" :tone="\App\Enums\CampaignStatus::tryFrom($step->to_status)?->tone() ?? 'neutral'">{{ \App\Enums\CampaignStatus::tryFrom($step->to_status)?->label() ?? $step->to_status }}</x-ui.badge>
                            <span class="ml-1 text-xs text-subtle">{{ $step->created_at->timezone($tz)->format('j M, H:i') }}</span>
                            <p class="mt-1 text-muted">{{ $step->user?->name ?? __('tasks.system') }}@if ($step->note) · {{ $step->note }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="approve-campaign" :title="__('messaging.approve_title')" :description="__('messaging.approve_help', ['count' => number_format($campaign->estimated_recipients), 'cost' => number_format((float) $campaign->estimated_cost, 2)])" icon="send" tone="warning">
        <p class="text-sm text-muted">{{ __('messaging.approve_checks') }}</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button x-on:click="open = false; $wire.approve()" loading="approve">{{ __('messaging.approve') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="reject-campaign" :title="__('messaging.reject')" icon="ban" tone="danger">
        <form wire:submit="reject" id="reject-campaign-form"><x-ui.textarea wire:model="note" :label="__('volunteers.reason')" rows="3" required /></form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="reject-campaign-form" variant="danger" loading="reject">{{ __('messaging.reject') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-dialog name="cancel-campaign" :title="__('messaging.cancel_title')" :description="__('messaging.cancel_help')" tone="danger">
        <x-slot:confirm><x-ui.button variant="danger" x-on:click="open = false; $wire.cancel()">{{ __('messaging.cancel') }}</x-ui.button></x-slot:confirm>
    </x-ui.confirm-dialog>
</div>
