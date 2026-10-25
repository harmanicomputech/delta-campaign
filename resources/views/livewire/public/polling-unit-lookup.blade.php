<div class="space-y-6">
    @unless ($hasUnits)
        <x-ui.alert tone="warning">{{ __('public.pu_not_loaded') }}</x-ui.alert>
    @endunless
    <div>
        <label for="pu-q" class="text-sm font-semibold text-fg">{{ __('public.pu_search') }}</label>
        <div class="relative mt-2">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-subtle" />
            <input id="pu-q" type="search" wire:model.live.debounce.400ms="query" placeholder="{{ __('public.pu_placeholder') }}" class="form-control h-12 pl-10 text-base">
        </div>
        <p class="mt-1.5 text-xs text-subtle">{{ __('public.pu_hint') }}</p>
    </div>
    <div class="flex items-center gap-3 text-xs font-semibold tracking-wide text-subtle uppercase"><span class="h-px flex-1 bg-line"></span>{{ __('public.or_browse') }}<span class="h-px flex-1 bg-line"></span></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.select wire:model.live="lgaId" label="LGA" :placeholder="__('users.choose')" :options="$lgas->all()" />
        <x-ui.select wire:model.live="wardId" :label="__('volunteers.ward')" :placeholder="$lgaId ? __('users.choose') : __('users.choose_lga_first')" :options="$wards->all()" :disabled="$wards->isEmpty()" />
    </div>
    <div wire:loading.class="opacity-60" aria-live="polite">
        @if ($results->isNotEmpty())
            <ul class="divide-y divide-line rounded-xl border border-line bg-surface">
                @foreach ($results as $unit)
                    <li class="px-5 py-3.5" wire:key="u-{{ $unit->id }}">
                        <p class="font-medium text-fg">{{ $unit->name }} @if ($unit->is_sample) <x-ui.sample-badge class="ml-1" /> @endif</p>
                        <p class="mt-0.5 text-sm text-muted"><span class="font-mono">{{ $unit->code }}</span> · {{ $unit->ward?->name }} · {{ $unit->lga?->name }}</p>
                    </li>
                @endforeach
            </ul>
        @elseif (mb_strlen(trim($query)) >= 3 || $wardId)
            <x-public.placeholder :title="__('public.pu_none')">{{ __('public.pu_none_help') }}</x-public.placeholder>
        @endif
    </div>
    <p class="text-xs text-subtle">{{ __('public.pu_disclaimer') }}</p>
</div>
