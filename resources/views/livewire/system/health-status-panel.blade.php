@php($icons = ['database' => 'database', 'postgis' => 'map', 'redis' => 'zap', 'cache' => 'layers', 'queue' => 'server'])
<div wire:poll.visible.30s="refresh">
    <x-ui.card :padding="false">
        <x-slot:header>
            <div class="min-w-0 flex-1">
                <h2 class="text-[0.9375rem] font-semibold tracking-tight text-fg">{{ __('health.title') }}</h2>
                <p class="mt-0.5 text-[0.8125rem] text-muted">{{ __('health.description') }}</p>
            </div>
        </x-slot:header>
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" icon="refresh-cw" wire:click="refresh" loading="refresh">{{ __('health.refresh') }}</x-ui.button>
        </x-slot:actions>

        <div @class([
            'flex items-center gap-3 px-5 py-3.5 text-sm font-medium',
            'bg-success-soft text-success' => $overallStatus === \App\Enums\HealthStatus::Ok,
            'bg-warning-soft text-warning' => $overallStatus === \App\Enums\HealthStatus::Degraded,
            'bg-danger-soft text-danger' => $overallStatus === \App\Enums\HealthStatus::Failed,
        ]) role="status">
            <x-icon :name="match ($overallStatus) { \App\Enums\HealthStatus::Ok => 'circle-check', \App\Enums\HealthStatus::Degraded => 'triangle-alert', default => 'circle-alert' }" class="size-[1.125rem]" />
            {{ __('health.overall.'.$overall) }}
        </div>

        <ul class="divide-y divide-line">
            @foreach ($checks as $check)
                @php($status = \App\Enums\HealthStatus::from($check['status']))
                <li class="flex items-center gap-4 px-5 py-3" wire:key="check-{{ $check['name'] }}">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-sunken text-muted">
                        <x-icon :name="$icons[$check['name']] ?? 'activity'" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-fg">{{ __('health.checks.'.$check['name']) }}</p>
                        @if ($check['detail']) <p class="truncate text-xs text-subtle">{{ $check['detail'] }}</p> @endif
                    </div>
                    @if ($check['latency_ms'] !== null)
                        <span class="hidden text-xs text-subtle tabular sm:inline">{{ number_format($check['latency_ms'], 1) }} ms</span>
                    @endif
                    <x-ui.badge :tone="$status->tone()" dot>{{ $status->label() }}</x-ui.badge>
                </li>
            @endforeach
        </ul>

        <x-slot:footer>
            <p class="flex items-center gap-1.5 text-xs text-subtle">
                <x-icon name="clock" class="size-3.5" />
                {{ __('health.last_checked', ['time' => \Illuminate\Support\Carbon::parse($checkedAt)->timezone(config('campaign.display_timezone'))->format('H:i:s')]) }}
            </p>
        </x-slot:footer>
    </x-ui.card>
</div>
