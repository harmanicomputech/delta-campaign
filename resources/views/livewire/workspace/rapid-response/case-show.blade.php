@php($tz = config('campaign.display_timezone'))
@php($s = $case->status->value)
<div>
    <x-ui.page-header :title="$case->title" :eyebrow="$case->reference">
        <x-slot:actions>
            @if ($case->priority->value !== 'normal') <x-ui.badge :tone="$case->priority->tone()">{{ $case->priority->label() }}</x-ui.badge> @endif
            <x-ui.badge :tone="$case->status->tone()" dot>{{ $case->status->label() }}</x-ui.badge>
            @if ($canManage)
                @if ($s === 'new') <x-ui.button icon="search" wire:click="startInvestigation" loading="startInvestigation">{{ __('rapid.start') }}</x-ui.button> @endif
                @if ($s === 'investigating') <x-ui.button icon="send" wire:click="requestApproval" loading="requestApproval">{{ $case->requires_legal ? __('rapid.send_legal') : __('rapid.send_approval') }}</x-ui.button> @endif
            @endif
            @if ($canApprove)
                @if ($s === 'legal_review') <x-ui.button icon="scale" x-on:click="$dispatch('open-modal', 'rr-legal')">{{ __('rapid.clear_legal') }}</x-ui.button> @endif
                @if ($s === 'awaiting_approval' && ! $isCreator) <x-ui.button icon="check" wire:click="approve" loading="approve" wire:confirm="{{ __('rapid.approve_confirm') }}">{{ __('rapid.approve') }}</x-ui.button> @endif
            @endif
            @if ($canManage && ! in_array($s, ['closed', 'rejected'], true))
                <x-ui.button variant="ghost" icon="archive" x-on:click="$dispatch('open-modal', 'rr-close')">{{ __('rapid.close') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>
    @if ($case->isOverdue()) <x-ui.alert tone="danger" class="mb-6">{{ __('rapid.overdue', ['time' => $case->deadline_at->timezone($tz)->format('j M, H:i')]) }}</x-ui.alert> @endif
    @if ($s === 'awaiting_approval' && $isCreator && $canApprove) <x-ui.alert tone="warning" class="mb-6">{{ __('rapid.not_own') }}</x-ui.alert> @endif
    @if ($case->content)
        <x-ui.alert tone="success" :title="__('rapid.draft_created')" class="mb-6">
            {{ __('rapid.draft_created_help') }}
            <a href="{{ route('workspace.content.show', $case->content) }}" class="font-medium underline">{{ $case->content->title_en }}</a> ({{ $case->content->status->label() }})
        </x-ui.alert>
    @elseif ($s === 'approved')
        <x-ui.alert tone="info" class="mb-6">{{ __('rapid.approved_no_draft') }}</x-ui.alert>
    @endif
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <div class="space-y-6">
            <x-ui.card :title="__('rapid.claim')">
                <p class="text-sm whitespace-pre-line text-fg">{{ $case->claim }}</p>
                @if ($case->source_urls)
                    <h3 class="mt-4 text-xs font-semibold tracking-wide text-muted uppercase">{{ __('rapid.sources') }}</h3>
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach ($case->source_urls as $url) <li class="truncate"><a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-primary underline">{{ $url }}</a></li> @endforeach
                    </ul>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('rapid.finding')" :description="__('rapid.finding_help')">
                @if ($canManage && in_array($s, ['new', 'investigating'], true))
                    <form wire:submit="saveFinding" class="space-y-4">
                        <x-ui.select wire:model="verdict" :label="__('rapid.verdict')" :placeholder="__('rapid.choose_verdict')" :options="\App\Enums\Verdict::options()" required />
                        <x-ui.textarea wire:model="finding" :label="__('rapid.finding')" :hint="__('rapid.finding_hint')" rows="6" required />
                        <x-ui.button type="submit" variant="secondary" loading="saveFinding">{{ __('rapid.save_finding') }}</x-ui.button>
                    </form>
                @elseif ($case->verdict)
                    <x-ui.badge :tone="$case->verdict->tone()">{{ $case->verdict->label() }}</x-ui.badge>
                    <div class="mt-3"><x-public.markdown :text="(string) $case->finding" /></div>
                @else
                    <p class="text-sm text-muted">{{ __('rapid.no_finding') }}</p>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('rapid.activity')" :padding="false">
                @if ($canManage)
                    <form wire:submit="addNote" class="flex flex-col gap-2 border-b border-line p-4 sm:flex-row sm:items-start">
                        <div class="flex-1"><x-ui.textarea wire:model="note" :label="__('rapid.add_note')" rows="2" /></div>
                        <x-ui.button type="submit" variant="secondary" loading="addNote" class="sm:mt-6">{{ __('rapid.post_note') }}</x-ui.button>
                    </form>
                @endif
                <ol class="divide-y divide-line">
                    @forelse ($case->notes as $entry)
                        <li class="flex gap-3 px-5 py-3 text-sm" wire:key="n-{{ $entry->id }}">
                            <x-icon :name="match ($entry->kind) { 'evidence' => 'paperclip', 'status' => 'git-commit-horizontal', default => 'message-square' }" class="mt-0.5 size-4 shrink-0 text-subtle" />
                            <div class="min-w-0 flex-1">
                                @if ($entry->kind === 'evidence')
                                    <a href="{{ route('workspace.rapid-response.evidence', [$case, $entry->id]) }}" class="font-medium text-primary underline">{{ $entry->original_name }}</a>
                                    <span class="block font-mono text-[0.7rem] text-subtle">SHA-256 {{ substr((string) $entry->sha256, 0, 16) }}…</span>
                                    @if ($entry->body) <p class="mt-1 text-muted">{{ $entry->body }}</p> @endif
                                @else
                                    <p @class(['text-fg whitespace-pre-line' => $entry->kind === 'note', 'text-muted' => $entry->kind === 'status'])>{{ $entry->body }}</p>
                                @endif
                                <span class="mt-0.5 block text-xs text-subtle">{{ $entry->user?->name }} · {{ $entry->created_at->timezone($tz)->format('j M, H:i') }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-muted">{{ __('rapid.no_activity') }}</li>
                    @endforelse
                </ol>
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card :title="__('content.details')">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('rapid.opened_by') }}</dt><dd class="text-fg">{{ $case->creator?->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('rapid.opened') }}</dt><dd class="text-fg">{{ $case->created_at->timezone($tz)->format('j M, H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('rapid.deadline') }}</dt><dd class="text-fg">{{ $case->deadline_at?->timezone($tz)->format('j M, H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('rapid.legal') }}</dt><dd class="text-fg">{{ $case->requires_legal ? ($case->legal_cleared_at ? __('rapid.legal_cleared') : __('rapid.legal_required')) : __('rapid.legal_not_needed') }}</dd></div>
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('rapid.assignee')">
                @if ($canManage)
                    <form wire:submit="assign" class="space-y-3">
                        <x-ui.select name="assigneeId" wire:model="assigneeId" :label="__('rapid.assignee')" :placeholder="__('rapid.unassigned')" :options="$officers" />
                        <x-ui.button type="submit" variant="secondary" size="sm" loading="assign">{{ __('rapid.assign') }}</x-ui.button>
                    </form>
                @else
                    <p class="text-sm text-fg">{{ $case->assignee?->name ?? __('rapid.unassigned') }}</p>
                @endif
            </x-ui.card>
            @if ($canManage && ! in_array($s, ['closed', 'rejected'], true))
                <x-ui.card :title="__('rapid.evidence')" :description="__('rapid.evidence_help')">
                    <form wire:submit="uploadEvidence" class="space-y-3">
                        <input type="file" wire:model="evidence" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain" aria-label="{{ __('rapid.evidence') }}" class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg">
                        @error('evidence') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                        <x-ui.input wire:model="evidenceDescription" :label="__('rapid.evidence_description')" optional />
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="upload" loading="uploadEvidence">{{ __('rapid.upload') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
    <x-ui.modal name="rr-close" :title="__('rapid.close')" icon="archive" tone="warning">
        <form wire:submit="close" id="rr-close-form" class="space-y-4">
            <x-ui.textarea wire:model="reason" :label="__('volunteers.reason')" rows="3" required />
            <x-ui.checkbox wire:model="noResponse" :label="__('rapid.no_response')" :description="__('rapid.no_response_help')" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="rr-close-form" loading="close">{{ __('rapid.close') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    <x-ui.modal name="rr-legal" :title="__('rapid.clear_legal')" icon="scale">
        <form wire:submit="clearLegal" id="rr-legal-form"><x-ui.textarea wire:model="reason" :label="__('rapid.legal_note')" rows="3" required /></form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="rr-legal-form" loading="clearLegal">{{ __('rapid.clear_legal') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
