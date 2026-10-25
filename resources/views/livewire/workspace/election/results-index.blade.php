@php($tz = config('campaign.display_timezone'))
<div wire:poll.30s.visible>
    <x-ui.page-header :title="__('election.results')" :description="__('election.results_intro')" />
    <x-ui.alert tone="warning" class="mb-6">{{ __('election.not_official_banner') }}</x-ui.alert>
    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
        @foreach (['review' => __('election.view_review'), 'all' => __('election.view_all'), 'discrepancies' => __('election.view_discrepancies')] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $view === $key ? 'true' : 'false' }}" wire:click="$set('view', '{{ $key }}')" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', 'bg-primary text-on-primary' => $view === $key, 'bg-surface-sunken text-muted hover:text-fg' => $view !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('election.search_results') }}" aria-label="{{ __('election.search_results') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            @if ($view === 'all')
                <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                    <option value="">{{ __('supporters.all_statuses') }}</option>
                    @foreach (\App\Enums\ResultStatus::options() as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
                </select>
            @endif
            <span class="text-xs text-muted sm:ml-auto">{{ trans_choice('election.official_recorded', $official, ['count' => number_format($official)]) }}</span>
        </div>
        @if ($submissions->isEmpty())
            <x-ui.empty-state icon="file-check" :title="$view === 'discrepancies' ? __('election.no_discrepancies') : __('election.no_results')" :description="$view === 'review' ? __('election.no_results_help') : null" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($submissions as $s)
                    <li wire:key="s-{{ $s->id }}">
                        <a href="{{ route('workspace.election.units.show', ['unit' => $s->polling_unit_id, 'submission' => $s->uuid]) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs text-subtle">{{ $s->reference }}</span><span class="font-medium text-fg">{{ $s->pollingUnit->code }}</span><span class="truncate text-sm text-muted">{{ $s->pollingUnit->name }}</span>@if ($s->is_sample) <x-ui.sample-badge /> @endif</span>
                                <span class="block text-xs text-subtle">{{ $s->lga?->name }} · {{ $s->submitter?->name }} · {{ strtoupper($s->source) }} · {{ $s->created_at->timezone($tz)->format('H:i') }} · {{ trans_choice('election.photos_count', $s->evidence_count, ['count' => $s->evidence_count]) }}</span>
                                @if ($s->issues) <span class="mt-1 block text-xs text-warning">{{ $s->issues[0]['message'] }}@if (count($s->issues) > 1) {{ __('election.more_issues', ['count' => count($s->issues) - 1]) }} @endif</span> @endif
                            </span>
                            <span class="text-right text-xs text-muted tabular-nums">{{ __('election.valid_short', ['n' => number_format($s->valid_votes)]) }}</span>
                            <x-ui.badge :tone="$s->status->tone()" size="sm" dot>{{ $s->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $submissions->links() }}</div>
        @endif
    </x-ui.card>
</div>
