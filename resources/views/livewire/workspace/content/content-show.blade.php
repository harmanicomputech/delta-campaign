@php($tz = config('campaign.display_timezone'))
@php($s = $item->status->value)
<div>
    <x-ui.page-header :title="$item->title_en" :eyebrow="__('content.type_'.$item->type->value)">
        <x-slot:actions>
            <x-ui.badge :tone="$item->status->tone()" dot>{{ $item->status->label() }}</x-ui.badge>
            @if ($item->isLive()) <x-ui.badge tone="success" icon="globe">{{ __('content.live') }}</x-ui.badge> @endif
            @if ($item->isLive() && $item->publicUrl())
                <x-ui.button variant="ghost" icon="external-link" :href="$item->publicUrl()" target="_blank">{{ __('content.view_live') }}</x-ui.button>
            @endif
            @can('content.create')
                @if (! in_array($s, ['in_review', 'archived'], true))
                    <x-ui.button variant="secondary" icon="pencil" :href="route('workspace.content.edit', $item)">{{ __('ui.edit') }}</x-ui.button>
                @endif
                @if (in_array($s, ['draft', 'rejected'], true))
                    <x-ui.button icon="send" wire:click="submit" loading="submit">{{ __('content.submit_review') }}</x-ui.button>
                @endif
            @endcan
            @if ($s === 'in_review')
                @can('content.review')
                    @unless ($isAuthor)
                        <x-ui.button variant="ghost" x-on:click="$dispatch('open-modal', 'reject-content')">{{ __('content.request_changes') }}</x-ui.button>
                        <x-ui.button icon="check" wire:click="approve" loading="approve">{{ __('content.approve') }}</x-ui.button>
                    @endunless
                @endcan
            @endif
            @can('content.publish')
                @if ($s === 'approved')
                    <x-ui.button icon="globe" x-on:click="$dispatch('open-modal', 'publish-content')">{{ __('content.publish') }}</x-ui.button>
                @endif
                @if ($item->isLive() || $s === 'scheduled')
                    <x-ui.button variant="ghost" icon="eye-off" x-on:click="$dispatch('open-modal', 'withdraw-content')">{{ __('content.withdraw') }}</x-ui.button>
                @endif
                @if (! $item->isLive() && in_array($s, ['draft', 'rejected', 'withdrawn', 'approved'], true))
                    <x-ui.button variant="ghost" icon="archive" wire:click="archive" wire:confirm="{{ __('content.archive_confirm') }}">{{ __('content.archive') }}</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($item->isLive() && $s !== 'published')
        <x-ui.alert tone="info" class="mb-6">{{ __('content.live_older', ['version' => $item->published_snapshot['version'] ?? '?']) }}</x-ui.alert>
    @endif
    @if ($s === 'rejected' && $item->review_note)
        <x-ui.alert tone="danger" :title="__('content.changes_requested')" class="mb-6">{{ $item->review_note }}</x-ui.alert>
    @elseif ($s === 'in_review' && $isAuthor)
        <x-ui.alert tone="warning" class="mb-6">{{ __('content.waiting_reviewer') }}</x-ui.alert>
    @elseif ($s === 'scheduled')
        <x-ui.alert tone="info" class="mb-6">{{ __('content.scheduled_for', ['date' => $item->publish_at?->timezone($tz)->format('j M Y, H:i')]) }}</x-ui.alert>
    @endif
    @if ($item->is_sample) <x-ui.alert tone="warning" class="mb-6">{{ __('public.sample_content') }}</x-ui.alert> @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px] [&>*]:min-w-0">
        <x-ui.card :title="__('content.preview')" :padding="false">
            <x-slot:actions>
                <select wire:model.live="previewLang" aria-label="{{ __('supporters.language') }}" class="form-control h-8 min-h-8 w-auto py-0 pr-8 text-xs"><option value="en">English</option><option value="pcm">Naijá</option></select>
            </x-slot:actions>
            <article class="p-6 sm:p-8">
                @if ($item->cover) <img src="{{ $item->cover->url(960) }}" alt="{{ $item->cover->alt() }}" class="mb-6 w-full rounded-xl"> @endif
                <h2 class="font-display text-2xl font-semibold tracking-tight text-fg">{{ $title }}</h2>
                @if ($item->summary_en) <p class="mt-2 text-muted">{{ $previewLang === 'pcm' && $item->summary_pcm ? $item->summary_pcm : $item->summary_en }}</p> @endif
                <x-public.markdown :text="$body" class="mt-6" />
            </article>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card :title="__('content.details')">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('content.slug') }}</dt><dd class="font-mono text-xs text-fg">/{{ $item->slug }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.created_by') }}</dt><dd class="text-right text-fg">{{ $item->creator?->name }}</dd></div>
                    @if ($item->approver) <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('content.approved_by') }}</dt><dd class="text-right text-fg">{{ $item->approver->name }}</dd></div> @endif
                    @if ($item->published_at) <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('public.published') }}</dt><dd class="text-right text-fg">{{ $item->published_at->timezone($tz)->format('j M Y, H:i') }}</dd></div> @endif
                    @if ($item->lga) <div class="flex justify-between gap-3"><dt class="text-muted">LGA</dt><dd class="text-right text-fg">{{ $item->lga->name }}</dd></div> @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('content.pidgin') }}</dt><dd class="text-right text-fg">{{ filled($item->body_pcm) ? __('content.translated') : __('content.english_only') }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('content.versions')" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($versions as $version)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm" wire:key="v-{{ $version->id }}">
                            <span class="min-w-0"><span class="font-medium text-fg">v{{ $version->version }}</span> <span class="text-xs text-muted">· {{ $version->user?->name }} · {{ $version->created_at->timezone($tz)->format('j M, H:i') }}</span>@if ($version->note)<span class="block truncate text-xs text-subtle">{{ $version->note }}</span>@endif</span>
                            @can('content.create')
                                @if ($version->version !== $item->version && ! in_array($s, ['in_review', 'archived'], true))
                                    <x-ui.button size="sm" variant="ghost" wire:click="restore({{ $version->id }})" wire:confirm="{{ __('content.restore_confirm') }}">{{ __('content.restore') }}</x-ui.button>
                                @endif
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('volunteers.history')" :padding="false">
                <ol class="divide-y divide-line">
                    @foreach ($history as $step)
                        <li class="px-5 py-2.5 text-sm">
                            <x-ui.badge size="sm" :tone="\App\Enums\ContentStatus::tryFrom($step->to_status)?->tone() ?? 'neutral'">{{ \App\Enums\ContentStatus::tryFrom($step->to_status)?->label() ?? $step->to_status }}</x-ui.badge>
                            <span class="ml-1 text-xs text-subtle">{{ $step->user?->name ?? __('tasks.system') }} · {{ $step->created_at->timezone($tz)->format('j M, H:i') }}</span>
                            @if ($step->note) <p class="mt-1 text-xs text-muted">{{ $step->note }}</p> @endif
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="reject-content" :title="__('content.request_changes')" icon="pencil" tone="warning">
        <form wire:submit="reject" id="reject-content-form"><x-ui.textarea wire:model="note" :label="__('content.review_note')" rows="3" required /></form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="reject-content-form" loading="reject">{{ __('content.request_changes') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    <x-ui.modal name="publish-content" :title="__('content.publish')" :description="__('content.publish_help')" icon="globe">
        <form wire:submit="publish" id="publish-content-form"><x-ui.input type="datetime-local" wire:model="publishAt" :label="__('content.publish_at')" :hint="__('content.publish_at_hint')" optional /></form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="publish-content-form" loading="publish">{{ __('content.publish') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    <x-ui.modal name="withdraw-content" :title="__('content.withdraw')" :description="__('content.withdraw_help')" icon="eye-off" tone="danger">
        <form wire:submit="withdraw" id="withdraw-content-form"><x-ui.textarea wire:model="note" :label="__('volunteers.reason')" rows="2" required /></form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="withdraw-content-form" variant="danger" loading="withdraw">{{ __('content.withdraw') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
