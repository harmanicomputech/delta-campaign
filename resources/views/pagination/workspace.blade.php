{{-- Livewire pagination for workspace tables (set via WithWorkspacePagination). --}}
@php($scrollTo = isset($scrollTo) ? $scrollTo : 'body')
<nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-3 px-5 py-3 sm:flex-row">
    <p class="text-[0.8125rem] text-muted">
        @if ($paginator->total() > 0)
            {{ __('ui.showing', ['from' => number_format($paginator->firstItem()), 'to' => number_format($paginator->lastItem()), 'total' => number_format($paginator->total())]) }}
        @endif
    </p>
    @if ($paginator->hasPages())
        <div class="flex items-center gap-1">
            <x-ui.button variant="secondary" size="sm" icon="chevron-left" wire:click="previousPage('{{ $paginator->getPageName() }}')" :disabled="$paginator->onFirstPage()" wire:loading.attr="disabled">
                <span class="sr-only sm:not-sr-only">{{ __('ui.previous') }}</span>
            </x-ui.button>
            <div class="hidden items-center gap-1 md:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-subtle">…</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="flex h-8 min-w-8 items-center justify-center rounded-md bg-primary-soft px-2 text-[0.8125rem] font-semibold text-primary-soft-fg tabular">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-[0.8125rem] text-muted tabular hover:bg-surface-sunken hover:text-fg">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>
            <x-ui.button variant="secondary" size="sm" icon-trailing="chevron-right" wire:click="nextPage('{{ $paginator->getPageName() }}')" :disabled="! $paginator->hasMorePages()" wire:loading.attr="disabled">
                <span class="sr-only sm:not-sr-only">{{ __('ui.next') }}</span>
            </x-ui.button>
        </div>
    @endif
</nav>
