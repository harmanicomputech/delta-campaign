@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('geography.imports')" :description="__('geography.imports_intro')" />

    <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)] [&>*]:min-w-0">
        <x-ui.card :title="__('geography.new_import')" :description="__('geography.new_import_help')">
            <div class="space-y-4">
                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong px-4 py-8 text-center transition-colors hover:border-primary">
                    <x-icon name="upload" class="size-6 text-subtle" />
                    <span class="text-sm font-medium text-fg" wire:loading.remove wire:target="file">{{ __('geography.choose_csv') }}</span>
                    <span class="text-sm text-muted" wire:loading wire:target="file">{{ __('geography.uploading') }}</span>
                    <span class="text-xs text-subtle">{{ __('geography.csv_limits', ['rows' => number_format((int) config('campaign.imports.max_rows')), 'mb' => (int) (config('campaign.imports.max_file_kb') / 1024)]) }}</span>
                    <input type="file" wire:model="file" accept=".csv,text/csv" class="sr-only">
                </label>
                @error('file') <p class="text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
                <p class="text-xs text-muted">{{ __('geography.nothing_written') }}</p>
                <a href="{{ route('workspace.geography.imports.template') }}" class="inline-flex items-center gap-1.5 text-[0.8125rem] font-medium text-primary hover:underline"><x-icon name="download" class="size-4" /> {{ __('geography.template') }}</a>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('geography.history')" :padding="false">
            @if ($jobs->isEmpty())
                <x-ui.empty-state icon="upload" :title="__('geography.no_imports')" :description="__('geography.no_imports_help')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($jobs as $job)
                        <li wire:key="j-{{ $job->id }}">
                            <a href="{{ route('workspace.geography.imports.show', $job) }}" class="flex flex-col gap-1.5 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-fg">{{ $job->original_name }} @if ($job->is_sample) <x-ui.sample-badge class="ml-1" /> @endif</span>
                                    <span class="block text-xs text-muted">{{ $job->creator?->name ?? __('tasks.system') }} · {{ $job->created_at->timezone($tz)->format('j M Y, H:i') }}</span>
                                </span>
                                <span class="text-xs tabular-nums text-muted">{{ __('geography.rows_summary', ['total' => number_format($job->total_rows), 'invalid' => number_format($job->invalid_rows)]) }}</span>
                                <x-ui.badge :tone="$job->status->tone()" size="sm" dot>{{ $job->status->label() }}</x-ui.badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line">{{ $jobs->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</div>
