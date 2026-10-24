<div @unless (\App\Support\LowData::enabled()) wire:poll.60s.visible @endunless>
    <x-ui.page-header :title="__('approvals.title')" :description="__('approvals.intro')">
        <x-slot:actions>
            <x-ui.badge :tone="$total > 0 ? 'warning' : 'success'" dot>{{ trans_choice('approvals.waiting', $total, ['count' => number_format($total)]) }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($queues === [])
        <x-ui.card><x-ui.empty-state icon="stamp" :title="__('approvals.no_queues')" :description="__('approvals.no_queues_help')" /></x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-2 [&>*]:min-w-0">
            @foreach ($queues as $queue)
                <x-ui.card :padding="false" wire:key="q-{{ $queue['key'] }}">
                    <x-slot:header>
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$queue['icon']" class="size-[1.125rem]" /></span>
                            <div class="min-w-0">
                                <h2 class="text-[0.9375rem] font-semibold tracking-tight text-fg">{{ $queue['label'] }}</h2>
                                <p class="text-[0.8125rem] text-muted">{{ trans_choice('approvals.waiting', $queue['count'], ['count' => number_format($queue['count'])]) }}</p>
                            </div>
                        </div>
                    </x-slot:header>
                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" :href="$queue['url']" icon-trailing="arrow-right">{{ __('approvals.open') }}</x-ui.button>
                    </x-slot:actions>
                    <ul class="divide-y divide-line">
                        @forelse ($queue['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-sunken/60">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-fg">{{ $item['title'] }}</span>
                                        <span class="block truncate text-xs text-muted">{{ $item['meta'] }}</span>
                                    </span>
                                    @if ($item['urgent']) <x-ui.badge tone="danger" size="sm" icon="clock">{{ __('approvals.urgent') }}</x-ui.badge> @endif
                                    <x-icon name="chevron-right" class="size-4 shrink-0 text-subtle" />
                                </a>
                            </li>
                        @empty
                            <li><x-ui.empty-state compact icon="circle-check" :title="__('approvals.queue_empty')" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
