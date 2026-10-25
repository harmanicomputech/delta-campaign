@php($tz = config('campaign.display_timezone'))
@php($s = $project->status->value)
<div>
    <x-ui.page-header :title="$project->title" :eyebrow="__('projects.record')">
        <x-slot:actions>
            <x-ui.badge :tone="$project->status->tone()" dot>{{ $project->status->label() }}</x-ui.badge>
            @if ($s === 'published') <x-ui.button variant="ghost" icon="external-link" :href="route('public.projects.show', $project->slug)" target="_blank">{{ __('content.view_live') }}</x-ui.button> @endif
            @can('projects.manage')
                @if ($s !== 'in_review') <x-ui.button variant="secondary" icon="pencil" :href="route('workspace.projects.edit', $project)">{{ __('ui.edit') }}</x-ui.button> @endif
                @if (in_array($s, ['draft', 'rejected'], true)) <x-ui.button icon="send" wire:click="submit" loading="submit">{{ __('content.submit_review') }}</x-ui.button> @endif
            @endcan
            @can('projects.approve')
                @if ($s === 'in_review' && ! $isAuthor)
                    <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'project-note')">{{ __('content.request_changes') }}</x-ui.button>
                    <x-ui.button icon="globe" wire:click="publish" loading="publish">{{ __('projects.approve_publish') }}</x-ui.button>
                @endif
                @if ($s === 'published') <x-ui.button variant="ghost" icon="eye-off" x-on:click="$dispatch('open-modal', 'project-withdraw')">{{ __('content.withdraw') }}</x-ui.button> @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>
    @if ($s === 'rejected' && $project->review_note) <x-ui.alert tone="danger" :title="__('content.changes_requested')" class="mb-6">{{ $project->review_note }}</x-ui.alert> @endif
    @if ($s === 'in_review' && $isAuthor) <x-ui.alert tone="warning" class="mb-6">{{ __('projects.waiting_other') }}</x-ui.alert> @endif
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <x-ui.card>
            @if ($project->cover) <img src="{{ $project->cover->url(960) }}" alt="{{ $project->cover->alt() }}" class="mb-6 w-full rounded-xl"> @endif
            <x-public.markdown :text="$project->description" />
        </x-ui.card>
        <div class="space-y-6">
            <x-ui.card :title="__('content.details')">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('public.category') }}</dt><dd class="text-fg">{{ __('projects.category_'.$project->category) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="text-right text-fg">{{ collect([$project->ward?->name, $project->lga?->name])->filter()->implode(' · ') ?: __('tasks.statewide') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('projects.verification') }}</dt><dd class="text-fg">{{ $project->verification->label() }}</dd></div>
                    <div><dt class="text-muted">{{ __('projects.source_note') }}</dt><dd class="mt-0.5 text-fg">{{ $project->source_note }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.created_by') }}</dt><dd class="text-fg">{{ $project->creator?->name }}</dd></div>
                    @if ($project->approver) <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('content.approved_by') }}</dt><dd class="text-fg">{{ $project->approver->name }}</dd></div> @endif
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('volunteers.history')" :padding="false">
                <ol class="divide-y divide-line">
                    @foreach ($history as $step)
                        <li class="px-5 py-2.5 text-sm"><x-ui.badge size="sm">{{ \App\Enums\ProjectStatus::tryFrom($step->to_status)?->label() ?? $step->to_status }}</x-ui.badge> <span class="text-xs text-subtle">{{ $step->user?->name }} · {{ $step->created_at->timezone($tz)->format('j M, H:i') }}</span>@if ($step->note)<p class="mt-1 text-xs text-muted">{{ $step->note }}</p>@endif</li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>
    @foreach (['project-note' => ['reject', __('content.request_changes')], 'project-withdraw' => ['withdraw', __('content.withdraw')]] as $modal => [$action, $label])
        <x-ui.modal :name="$modal" :title="$label" icon="pencil" tone="warning">
            <form wire:submit="{{ $action }}" id="{{ $modal }}-form"><x-ui.textarea wire:model="note" :label="__('volunteers.reason')" rows="3" required /></form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" :form="$modal.'-form'" loading="{{ $action }}">{{ $label }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endforeach
</div>
