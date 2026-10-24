@php($tones = ['verified' => 'success', 'pending' => 'warning', 'review' => 'info'])
<div>
    <x-ui.card :padding="false">
        <x-slot:header>
            <div class="flex min-w-0 items-center gap-2">
                <h3 class="text-[0.9375rem] font-semibold tracking-tight text-fg">Records</h3>
                <x-ui.sample-badge />
            </div>
        </x-slot:header>
        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search reference, name or area" aria-label="Search records" class="form-control h-9 min-h-9 pl-9">
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <select wire:model.live="status" aria-label="Filter by status" class="form-control h-9 min-h-9 appearance-none pr-8 sm:w-40">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value)
                            <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevrons-up-down" class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-subtle" />
                </div>
                @if ($search !== '' || $status !== '')
                    <x-ui.button variant="ghost" size="sm" icon="x" wire:click="clearFilters">Clear</x-ui.button>
                @endif
            </div>
            <div wire:loading.flex wire:target="search, status, sortBy, gotoPage, nextPage, previousPage" class="hidden items-center gap-2 text-xs text-subtle sm:ml-auto">
                <x-ui.spinner class="size-4" /> {{ __('ui.loading') }}
            </div>
        </div>

        {{-- Bulk actions --}}
        @if ($selected !== [])
            <div class="flex items-center gap-3 border-b border-line bg-primary-soft/60 px-5 py-2.5 text-sm animate-fade-in">
                <span class="font-medium text-primary-soft-fg">{{ trans_choice('ui.rows_selected', count($selected), ['count' => count($selected)]) }}</span>
                <x-ui.button variant="secondary" size="sm" icon="check" x-on:click="$dispatch('open-modal', 'confirm-bulk')">Mark reviewed</x-ui.button>
                <x-ui.button variant="ghost" size="sm" wire:click="clearSelection">{{ __('ui.clear_selection') }}</x-ui.button>
            </div>
        @endif

        <div wire:loading.class="opacity-60" wire:target="search, status, sortBy, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($records->isEmpty())
                <x-ui.empty-state icon="search" title="No records match" description="Try a different search term or clear the filters.">
                    <x-ui.button variant="secondary" size="sm" wire:click="clearFilters">Clear filters</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.table caption="Sample records">
                    <x-slot:head>
                        <tr>
                            <th scope="col" class="w-10 border-b border-line py-2.5 pl-5">
                                <input type="checkbox" aria-label="Select all rows on this page"
                                       x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...@js($records->pluck('reference'))])] : $wire.selected.filter(r => !@js($records->pluck('reference')).includes(r))"
                                       :checked="@js($records->pluck('reference')).every(r => $wire.selected.includes(r))"
                                       class="size-4 rounded accent-[var(--c-primary)]">
                            </th>
                            <x-ui.th column="reference" sortable :sort="$sort" :direction="$direction">Reference</x-ui.th>
                            <x-ui.th column="name" sortable :sort="$sort" :direction="$direction">Name</x-ui.th>
                            <x-ui.th column="area" sortable :sort="$sort" :direction="$direction" class="hidden md:table-cell">Area</x-ui.th>
                            <x-ui.th column="status" sortable :sort="$sort" :direction="$direction">Status</x-ui.th>
                            <x-ui.th column="updated_at" sortable :sort="$sort" :direction="$direction" class="hidden lg:table-cell">Updated</x-ui.th>
                            <x-ui.th align="right"><span class="sr-only">{{ __('ui.actions') }}</span></x-ui.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($records as $record)
                        <tr wire:key="row-{{ $record['reference'] }}">
                            <x-ui.td class="w-10">
                                <input type="checkbox" wire:model.live="selected" value="{{ $record['reference'] }}" aria-label="Select {{ $record['reference'] }}" class="size-4 rounded accent-[var(--c-primary)]">
                            </x-ui.td>
                            <x-ui.td class="font-mono text-[0.8125rem] text-muted">{{ $record['reference'] }}</x-ui.td>
                            <x-ui.td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$record['name']" size="sm" class="ring-0" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ $record['name'] }}</p>
                                        <p class="truncate text-xs text-subtle md:hidden">{{ $record['area'] }}</p>
                                    </div>
                                </div>
                            </x-ui.td>
                            <x-ui.td class="hidden text-muted md:table-cell">{{ $record['area'] }}</x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$tones[$record['status']]" dot>{{ ucfirst($record['status']) }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="hidden text-muted tabular lg:table-cell">{{ $record['updated_at']->timezone(config('campaign.display_timezone'))->format('j M Y, H:i') }}</x-ui.td>
                            <x-ui.td align="right">
                                <x-ui.button variant="ghost" size="sm" square icon="arrow-up-right" wire:click="inspect('{{ $record['reference'] }}')">
                                    <span class="sr-only">{{ __('ui.view_details') }}: {{ $record['reference'] }}</span>
                                </x-ui.button>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>

        <div class="border-t border-line">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.confirm-dialog name="confirm-bulk" title="Mark selected rows as reviewed?" tone="warning"
                         description="This demonstrates the confirmation step required for bulk actions. Sample data — nothing is saved.">
        <x-slot:confirm>
            <x-ui.button x-on:click="open = false; $wire.markReviewed()">Mark reviewed</x-ui.button>
        </x-slot:confirm>
    </x-ui.confirm-dialog>

    <x-ui.slide-over name="sample-record" title="Record details" description="Sample record — not a real person.">
        @if ($this->inspected)
            @php($record = $this->inspected)
            <div class="flex items-center gap-4">
                <x-ui.avatar :name="$record['name']" size="lg" class="ring-0" />
                <div>
                    <p class="text-base font-semibold text-fg">{{ $record['name'] }}</p>
                    <p class="font-mono text-[0.8125rem] text-subtle">{{ $record['reference'] }}</p>
                </div>
            </div>
            <dl class="mt-6 divide-y divide-line rounded-lg border border-line text-sm">
                @foreach (['Status' => ucfirst($record['status']), 'Area' => $record['area'], 'Source' => strtoupper($record['source']), 'Updated' => $record['updated_at']->timezone(config('campaign.display_timezone'))->format('j M Y, H:i')] as $term => $value)
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-muted">{{ $term }}</dt>
                        <dd class="font-medium text-fg">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <x-ui.alert tone="neutral" class="mt-6">Record views on real modules are access-logged and limited to the viewer's geographic scope.</x-ui.alert>
        @endif
    </x-ui.slide-over>
</div>
