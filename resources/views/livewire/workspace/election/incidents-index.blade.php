@php($tz = config('campaign.display_timezone'))
<div wire:poll.20s.visible>
    <x-ui.page-header :title="__('election.incidents')" :description="__('election.incidents_intro')" />
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('election.search_incidents') }}" aria-label="{{ __('election.search_incidents') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="open">{{ __('election.open_incidents') }}</option><option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach (\App\Enums\IncidentStatus::options() as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
            </select>
            <select wire:model.live="severity" aria-label="{{ __('election.severity') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('election.all_severities') }}</option>
                @foreach (\App\Enums\IncidentSeverity::options() as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
            </select>
            <select wire:model.live="category" aria-label="{{ __('election.category') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('election.all_categories') }}</option>
                @foreach (\App\Enums\IncidentCategory::options() as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
            </select>
        </div>
        @if ($incidents->isEmpty())
            <x-ui.empty-state icon="shield-check" :title="__('election.no_incidents')" :description="__('election.no_incidents_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($incidents as $incident)
                    <li wire:key="i-{{ $incident->id }}">
                        <a href="{{ route('workspace.election.incidents.show', $incident) }}" class="flex flex-col gap-2 px-5 py-3.5 hover:bg-surface-sunken/60 sm:flex-row sm:items-center sm:gap-4">
                            <x-ui.badge :tone="$incident->severity->tone()" size="sm" class="w-fit">{{ $incident->severity->label() }}</x-ui.badge>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs text-subtle">{{ $incident->reference }}</span><span class="font-medium text-fg">{{ $incident->category->label() }}</span>@if ($incident->evidence_count) <x-icon name="paperclip" class="size-3.5 text-subtle" /> @endif @if ($incident->is_sample) <x-ui.sample-badge /> @endif</span>
                                <span class="block truncate text-sm text-muted">{{ $incident->description }}</span>
                                <span class="block text-xs text-subtle">{{ $incident->pollingUnit?->code }} · {{ $incident->lga?->name }} · {{ $incident->occurred_at->timezone($tz)->format('H:i') }} · {{ strtoupper($incident->source) }}@if ($incident->assignee) · {{ $incident->assignee->name }} @endif</span>
                            </span>
                            <x-ui.badge :tone="$incident->status->tone()" size="sm" dot>{{ $incident->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $incidents->links() }}</div>
        @endif
    </x-ui.card>
</div>
