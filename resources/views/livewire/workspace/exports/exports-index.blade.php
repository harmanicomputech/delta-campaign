<div @if ($pending) wire:poll.4s @endif>
    <x-ui.page-header :title="__('supporters.exports')" :description="__('supporters.exports_intro')" />
    <x-ui.card :padding="false">
        @if ($exports->isEmpty())
            <x-ui.empty-state icon="download" :title="__('supporters.no_exports')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($exports as $export)
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center" wire:key="e-{{ $export->id }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-sunken text-muted"><x-icon name="file-text" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-fg">{{ ucfirst($export->type) }} · {{ $export->created_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }}</p>
                            <p class="text-xs text-subtle">
                                @if ($export->row_count !== null) {{ trans_choice('supporters.rows', $export->row_count, ['count' => number_format($export->row_count)]) }} · @endif
                                {{ $export->include_contact ? __('supporters.include_contact') : __('supporters.masked') }}
                                @if ($export->expires_at && $export->status === \App\Enums\ExportStatus::Completed) · {{ __('supporters.expires', ['time' => $export->expires_at->diffForHumans()]) }} @endif
                                @if ($export->failure_reason) · <span class="text-danger">{{ $export->failure_reason }}</span> @endif
                            </p>
                        </div>
                        <x-ui.badge :tone="$export->status->tone()" dot>{{ $export->status->label() }}</x-ui.badge>
                        @if ($export->isDownloadable())
                            <x-ui.button variant="secondary" size="sm" icon="download" :href="$downloadUrl($export)">{{ __('supporters.download') }}</x-ui.button>
                        @elseif (in_array($export->status, [\App\Enums\ExportStatus::Queued, \App\Enums\ExportStatus::Running], true))
                            <x-ui.spinner class="size-4" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-line">{{ $exports->links() }}</div>
    </x-ui.card>
</div>
