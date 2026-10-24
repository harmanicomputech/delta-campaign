@php($tz = config('campaign.display_timezone'))
<div @if ($working) wire:poll.3s @endif>
    <x-ui.page-header :title="$import->original_name" :eyebrow="__('geography.import')">
        <x-slot:actions>
            <x-ui.badge :tone="$import->status->tone()" dot>{{ $import->status->label() }}</x-ui.badge>
            @if ($import->error_report_path)
                <x-ui.button variant="secondary" icon="download" :href="route('workspace.geography.imports.report', $import)">{{ __('geography.download_issues') }}</x-ui.button>
            @endif
            @if (in_array($import->status->value, ['uploaded', 'validated'], true))
                <x-ui.button variant="ghost" wire:click="cancel" wire:confirm="{{ __('geography.cancel_confirm') }}">{{ __('geography.cancel_import') }}</x-ui.button>
            @endif
            @if ($import->status->canConfirm() && $import->valid_rows > 0)
                <x-ui.button icon="check" x-on:click="$dispatch('open-modal', 'confirm-import')">{{ __('geography.confirm_import') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($working)
        <x-ui.alert tone="info" class="mb-6"><span class="inline-flex items-center gap-2"><x-ui.spinner class="size-4" /> {{ $import->status->value === 'importing' ? __('geography.applying') : __('geography.validating') }}</span></x-ui.alert>
    @elseif ($import->status->value === 'failed')
        <x-ui.alert tone="danger" :title="__('geography.failed')" class="mb-6">{{ $import->failure_reason }}</x-ui.alert>
    @elseif ($import->status->value === 'completed')
        <x-ui.alert tone="success" :title="__('geography.completed')" class="mb-6">{{ __('geography.completed_body', ['created' => number_format($import->created_count), 'updated' => number_format($import->updated_count), 'unchanged' => number_format($import->unchanged_count)]) }}</x-ui.alert>
    @endif
    @if ($identical && ! in_array($import->status->value, ['completed', 'cancelled'], true))
        <x-ui.alert tone="warning" class="mb-6">{{ __('geography.identical', ['date' => $identical->completed_at?->timezone($tz)->format('j M Y')]) }}</x-ui.alert>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5 [&>*]:min-w-0">
        <x-ui.stat :label="__('geography.total_rows')" :value="number_format($import->total_rows)" icon="file-text" />
        <x-ui.stat :label="__('geography.valid_rows')" :value="number_format($import->valid_rows)" icon="circle-check" />
        <x-ui.stat :label="__('geography.invalid_rows')" :value="number_format($import->invalid_rows)" icon="circle-x" />
        <x-ui.stat :label="__('geography.new_units')" :value="number_format((int) ($actions['create'] ?? 0))" icon="plus" />
        <x-ui.stat :label="__('geography.changed_units')" :value="number_format((int) ($actions['update'] ?? 0))" icon="pencil" :hint="__('geography.unchanged', ['count' => number_format((int) ($actions['unchanged'] ?? 0))])" class="col-span-2 lg:col-span-1" />
    </div>

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
            @foreach (['all', 'invalid', 'warnings', 'create', 'update', 'unchanged'] as $key)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        @class(['rounded-full border px-3 py-1 text-xs font-medium transition-colors', 'border-primary bg-primary-soft text-primary-soft-fg' => $filter === $key, 'border-line text-muted hover:text-fg' => $filter !== $key])>{{ __('geography.filter_'.$key) }}</button>
            @endforeach
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty-state compact icon="file-check" :title="__('geography.no_rows')" />
        @else
            <x-ui.table :caption="__('geography.rows')">
                <x-slot:head>
                    <tr>
                        <x-ui.th class="w-16">{{ __('geography.row') }}</x-ui.th>
                        <x-ui.th>{{ __('geography.polling_unit') }}</x-ui.th>
                        <x-ui.th class="hidden md:table-cell">{{ __('geography.ward') }}</x-ui.th>
                        <x-ui.th>{{ __('geography.result') }}</x-ui.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr wire:key="r-{{ $row->id }}">
                        <x-ui.td class="tabular-nums text-subtle">{{ $row->row_number }}</x-ui.td>
                        <x-ui.td><span class="font-mono text-xs">{{ $row->payload['pu_code'] ?? '—' }}</span><span class="block text-sm text-fg">{{ $row->payload['pu_name'] ?? '' }}</span></x-ui.td>
                        <x-ui.td class="hidden text-muted md:table-cell">{{ $row->payload['ward_name'] ?? '' }} <span class="font-mono text-xs">{{ $row->payload['ward_code'] ?? '' }}</span><span class="block text-xs">{{ $row->payload['lga'] ?? '' }}</span></x-ui.td>
                        <x-ui.td>
                            @if ($row->status->value === 'invalid')
                                <x-ui.badge tone="danger" size="sm">{{ __('geography.invalid') }}</x-ui.badge>
                            @else
                                <x-ui.badge :tone="['create' => 'success', 'update' => 'info', 'unchanged' => 'neutral'][$row->action?->value] ?? 'neutral'" size="sm">{{ __('geography.action_'.($row->action?->value ?? 'unchanged')) }}</x-ui.badge>
                            @endif
                            @foreach ($row->errors ?? [] as $message) <p class="mt-1 text-xs text-danger">{{ $message }}</p> @endforeach
                            @foreach ($row->warnings ?? [] as $message) <p class="mt-1 text-xs text-warning">{{ $message }}</p> @endforeach
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="border-t border-line">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>

    @if ($import->status->canConfirm())
        <x-ui.modal name="confirm-import" :title="__('geography.confirm_import')" :description="__('geography.confirm_help', ['valid' => number_format($import->valid_rows), 'invalid' => number_format($import->invalid_rows)])" icon="upload" tone="warning">
            <ul class="space-y-1.5 text-sm text-muted">
                <li>• {{ __('geography.confirm_point_1') }}</li>
                <li>• {{ __('geography.confirm_point_2') }}</li>
                <li>• {{ __('geography.confirm_point_3') }}</li>
            </ul>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button x-on:click="open = false; $wire.confirm()" loading="confirm">{{ __('geography.confirm_import') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
