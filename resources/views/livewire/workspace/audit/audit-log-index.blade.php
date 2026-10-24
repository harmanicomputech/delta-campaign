<div>
    <x-ui.page-header :title="__('audit.title')" :description="__('audit.intro')">
        <x-slot:actions>
            @can('audit.verify')
                <x-ui.button variant="secondary" icon="shield-check" wire:click="verifyChain" loading="verifyChain">{{ __('audit.verify') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($verification)
        <x-ui.alert :tone="$verification['valid'] ? 'success' : 'danger'" :title="$verification['valid'] ? __('audit.valid') : __('audit.broken')" class="mb-6">
            @if ($verification['valid'])
                {{ __('audit.valid_detail', ['count' => number_format($verification['checked']), 'id' => $verification['last_id'] ?? '—']) }}
                <span class="mt-1 block font-mono text-xs break-all">{{ $verification['last_hash'] }}</span>
            @else
                {{ __('audit.broken_detail', ['id' => $verification['broken_id']]) }} {{ $verification['reason'] }}
            @endif
        </x-ui.alert>
    @endif

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 md:flex-row md:items-end">
            <x-ui.select wire:model.live="actionFilter" :label="__('audit.area')" :placeholder="__('audit.all_areas')" :options="$groups->mapWithKeys(fn ($g) => [$g.'.' => $g])->all()" class="md:w-56" />
            <x-ui.input wire:model.live="from" type="date" :label="__('audit.from')" class="md:w-44" />
            <x-ui.input wire:model.live="to" type="date" :label="__('audit.to')" class="md:w-44" />
        </div>

        @if ($entries->isEmpty())
            <x-ui.empty-state icon="scroll-text" :title="__('audit.none')" />
        @else
            <x-ui.table :caption="__('audit.title')" dense>
                <x-slot:head>
                    <tr>
                        <x-ui.th>#</x-ui.th>
                        <x-ui.th>{{ __('audit.when') }}</x-ui.th>
                        <x-ui.th>{{ __('audit.action') }}</x-ui.th>
                        <x-ui.th class="hidden md:table-cell">{{ __('audit.actor') }}</x-ui.th>
                        <x-ui.th class="hidden lg:table-cell">{{ __('audit.subject') }}</x-ui.th>
                        <x-ui.th align="right"><span class="sr-only">{{ __('ui.actions') }}</span></x-ui.th>
                    </tr>
                </x-slot:head>
                @foreach ($entries as $row)
                    <tr wire:key="al-{{ $row->id }}">
                        <x-ui.td class="font-mono text-xs text-subtle">{{ $row->id }}</x-ui.td>
                        <x-ui.td class="text-xs whitespace-nowrap text-muted">{{ $row->occurred_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i:s') }}</x-ui.td>
                        <x-ui.td class="font-mono text-[0.8125rem]">{{ $row->action }}</x-ui.td>
                        <x-ui.td class="hidden text-muted md:table-cell">{{ $row->actor?->name ?? $row->actor_label }}</x-ui.td>
                        <x-ui.td class="hidden font-mono text-xs text-subtle lg:table-cell">{{ $row->subject_type ? class_basename($row->subject_type).' #'.$row->subject_id : '—' }}</x-ui.td>
                        <x-ui.td align="right">
                            <x-ui.button variant="ghost" size="sm" square icon="arrow-up-right" wire:click="inspect({{ $row->id }})"><span class="sr-only">{{ __('ui.view_details') }} #{{ $row->id }}</span></x-ui.button>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
        <div class="border-t border-line">{{ $entries->links() }}</div>
    </x-ui.card>

    <x-ui.slide-over name="audit-entry" :title="$entry ? __('audit.entry', ['id' => $entry->id]) : ''" width="lg">
        @if ($entry)
            <dl class="divide-y divide-line rounded-lg border border-line text-sm">
                @foreach ([
                    __('audit.action') => $entry->action,
                    __('audit.when') => $entry->occurred_at->timezone(config('campaign.display_timezone'))->format('j M Y, H:i:s.u T'),
                    __('audit.actor') => $entry->actor?->name ?? $entry->actor_label,
                    __('audit.subject') => $entry->subject_type ? $entry->subject_type.' #'.$entry->subject_id : '—',
                    'IP' => $entry->ip ?? '—',
                    __('audit.request') => $entry->request_id ?? '—',
                ] as $term => $value)
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="shrink-0 text-muted">{{ $term }}</dt><dd class="min-w-0 text-right font-medium break-all text-fg">{{ $value }}</dd></div>
                @endforeach
            </dl>
            @foreach (['changes' => __('audit.changes'), 'context' => __('audit.context')] as $field => $label)
                @if ($entry->{$field})
                    <h3 class="mt-6 mb-2 text-sm font-semibold text-fg">{{ $label }}</h3>
                    <pre class="scroll-thin max-h-64 overflow-auto rounded-lg bg-surface-sunken p-3 font-mono text-xs text-fg">{{ json_encode($entry->{$field}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            @endforeach
            <h3 class="mt-6 mb-2 text-sm font-semibold text-fg">{{ __('audit.chain') }}</h3>
            <div class="space-y-2 rounded-lg bg-surface-sunken p-3 font-mono text-[0.6875rem] break-all text-muted">
                <p><span class="text-subtle">prev</span> {{ $entry->previous_hash ?? 'GENESIS' }}</p>
                <p><span class="text-subtle">hash</span> <span class="text-fg">{{ $entry->hash }}</span></p>
            </div>
        @endif
    </x-ui.slide-over>
</div>
