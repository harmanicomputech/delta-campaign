@php($tz = config('campaign.display_timezone'))
@php($start = $event->starts_at->timezone($tz))
@php($s = $event->status->value)
<div>
    <x-ui.page-header :title="$event->title_en" :eyebrow="$start->format('D j M Y, g:ia')">
        <x-slot:actions>
            <x-ui.badge :tone="$event->status->tone()" dot>{{ $event->status->label() }}</x-ui.badge>
            @if ($event->published_at) <x-ui.button variant="ghost" icon="external-link" :href="route('public.events.show', $event->slug)" target="_blank">{{ __('content.view_live') }}</x-ui.button> @endif
            @if ($canManage)
                @if ($s !== 'cancelled') <x-ui.button variant="secondary" icon="pencil" :href="route('workspace.events.edit', $event)">{{ __('ui.edit') }}</x-ui.button> @endif
                @if ($s === 'draft') <x-ui.button icon="globe" wire:click="publish" loading="publish">{{ __('events.publish') }}</x-ui.button> @endif
                @if ($s === 'published')
                    <x-ui.button variant="secondary" icon="user-plus" x-on:click="$dispatch('open-modal', 'event-walk-in')">{{ __('events.walk_in') }}</x-ui.button>
                    <x-ui.button icon="scan-line" :href="route('workspace.events.check-in')">{{ __('events.check_in') }}</x-ui.button>
                @endif
                @if (in_array($s, ['draft', 'published'], true)) <x-ui.button variant="danger" icon="x" x-on:click="$dispatch('open-modal', 'event-cancel')">{{ __('events.cancel_event') }}</x-ui.button> @endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>
    @if ($s === 'cancelled') <x-ui.alert tone="danger" :title="__('events.cancelled')" class="mb-6">{{ $event->cancellation_reason }}</x-ui.alert> @endif
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('events.stat_registered')" :value="number_format(($counts['registered'] ?? 0) + ($counts['attended'] ?? 0))" :hint="$event->capacity ? __('events.of_capacity', ['capacity' => number_format($event->capacity)]) : __('events.no_limit')" />
        <x-ui.stat :label="__('events.stat_attended')" :value="number_format($counts['attended'] ?? 0)" />
        <x-ui.stat :label="__('events.stat_cancelled')" :value="number_format($counts['cancelled'] ?? 0)" />
    </div>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] [&>*]:min-w-0">
        <div class="space-y-6">
            @if ($registrations !== null)
                <x-ui.card :title="__('events.registrations')" :padding="false">
                    <x-slot:actions>
                        <select wire:model.live="filter" aria-label="{{ __('supporters.status') }}" class="form-control h-8 min-h-8 w-auto pr-8 text-sm">
                            <option value="">{{ __('supporters.all_statuses') }}</option>
                            @foreach (\App\Enums\RegistrationStatus::options() as $v => $l) <option value="{{ $v }}">{{ $l }}</option> @endforeach
                        </select>
                    </x-slot:actions>
                    @if ($registrations->isEmpty())
                        <x-ui.empty-state icon="users" :title="__('events.no_registrations')" />
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($registrations as $r)
                                <li wire:key="r-{{ $r->id }}" class="flex items-center gap-3 px-5 py-3 text-sm">
                                    <span class="min-w-0 flex-1"><span class="block font-medium text-fg">{{ $r->full_name }}</span><span class="block font-mono text-xs text-subtle">{{ $r->maskedPhone() }} · {{ __('events.source_'.$r->source) }}</span></span>
                                    <span class="hidden text-xs text-subtle sm:block">{{ ($r->checked_in_at ?? $r->created_at)->timezone($tz)->format('j M, H:i') }}</span>
                                    <x-ui.badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-ui.badge>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-line">{{ $registrations->links() }}</div>
                    @endif
                </x-ui.card>
            @else
                <x-ui.alert tone="info">{{ __('events.registrations_restricted') }}</x-ui.alert>
            @endif
            <x-ui.card :title="__('events.description')"><x-public.markdown :text="$event->description_en" /></x-ui.card>
        </div>
        <x-ui.card :title="__('content.details')">
            @if ($event->cover) <img src="{{ $event->cover->url(480) }}" alt="{{ $event->cover->alt() }}" class="mb-4 w-full rounded-lg"> @endif
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('events.venue') }}</dt><dd class="text-right text-fg">{{ $event->venue }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('supporters.area') }}</dt><dd class="text-right text-fg">{{ collect([$event->ward?->name, $event->lga?->name])->filter()->implode(' · ') ?: __('tasks.statewide') }}</dd></div>
                @if ($event->ends_at) <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('events.ends_at') }}</dt><dd class="text-fg">{{ $event->ends_at->timezone($tz)->format('j M, g:ia') }}</dd></div> @endif
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('events.registration') }}</dt><dd class="text-fg">{{ $event->registration_open ? __('events.open') : __('events.closed_short') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('messaging.created_by') }}</dt><dd class="text-fg">{{ $event->creator?->name }}</dd></div>
            </dl>
        </x-ui.card>
    </div>
    @if ($canManage)
        <x-ui.modal name="event-cancel" :title="__('events.cancel_event')" icon="triangle-alert" tone="danger">
            <p class="mb-4 text-sm text-muted">{{ __('events.cancel_help') }}</p>
            <form wire:submit="cancel" id="event-cancel-form"><x-ui.textarea wire:model="reason" :label="__('volunteers.reason')" rows="3" required /></form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" type="submit" form="event-cancel-form" loading="cancel">{{ __('events.cancel_event') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
        <x-ui.modal name="event-walk-in" :title="__('events.walk_in')" icon="user-plus">
            <form wire:submit="addAttendee" id="event-walk-in-form" class="space-y-4">
                <x-ui.input wire:model="attendeeName" :label="__('public.full_name')" required />
                <x-ui.phone-input name="attendeePhone" wire:model="attendeePhone" :label="__('public.phone')" required />
                <p class="text-xs text-muted">{{ __('events.walk_in_help') }}</p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="event-walk-in-form" loading="addAttendee">{{ __('events.walk_in_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
