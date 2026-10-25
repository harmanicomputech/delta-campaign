@php($tz = config('campaign.display_timezone'))
<div class="mx-auto max-w-2xl">
    @if ($assignment === null)
        <x-ui.page-header :title="__('election.my_unit')" />
        <x-ui.card><x-ui.empty-state icon="shield-check" :title="__('election.no_assignment')" :description="__('election.no_assignment_help')" /></x-ui.card>
    @else
        @php($unit = $assignment->pollingUnit)
        @php($election = $assignment->election)
        <x-ui.page-header :title="$unit->name" :eyebrow="$unit->code.' · '.$election->name" />
        <x-ui.alert tone="info" class="mb-4">{{ __('election.agent_notice') }}</x-ui.alert>
        <nav class="mb-4 grid grid-cols-4 gap-1 rounded-xl bg-surface-sunken p-1 text-sm" aria-label="{{ __('election.sections') }}">
            @foreach (['' => __('election.tab_today'), 'turnout' => __('election.tab_turnout'), 'incident' => __('election.tab_incident'), 'result' => __('election.tab_result')] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['rounded-lg px-2 py-2 font-medium', 'bg-surface text-fg shadow-xs' => $tab === $key, 'text-muted hover:text-fg' => $tab !== $key]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($tab === '')
            <div class="space-y-4">
                <x-ui.card :title="__('election.step_arrive')">
                    @if ($assignment->arrived_at)
                        <p class="flex items-center gap-2 text-sm text-fg"><x-icon name="circle-check" class="size-5 text-success" /> {{ __('election.arrived_at', ['time' => $assignment->arrived_at->timezone($tz)->format('H:i')]) }}</p>
                    @else
                        <p class="mb-3 text-sm text-muted">{{ __('election.arrive_help') }}</p>
                        <div x-data="{ busy: false, send() { this.busy = true; const done = (p) => $wire.arrive(p?.coords.latitude ?? null, p?.coords.longitude ?? null, p ? Math.round(p.coords.accuracy) : null).finally(() => this.busy = false);
                            if (!navigator.geolocation) return done(null); navigator.geolocation.getCurrentPosition(done, () => done(null), { timeout: 8000, maximumAge: 60000 }); } }">
                            <x-ui.button icon="map-pin" class="w-full sm:w-auto" x-on:click="send()" x-bind:disabled="busy">{{ __('election.i_have_arrived') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
                <x-ui.card :title="__('election.step_checklist')" :description="__('election.checklist_help')">
                    <form wire:submit="saveChecklist" class="space-y-3">
                        @foreach ($election->checklist as $item)
                            <x-ui.checkbox wire:model="answers.{{ $item['key'] }}" :label="$item['label']" />
                        @endforeach
                        <x-ui.button type="submit" variant="secondary" loading="saveChecklist">{{ $assignment->checklist_completed_at ? __('election.update_checklist') : __('election.save_checklist') }}</x-ui.button>
                        @if ($assignment->checklist_completed_at) <p class="text-xs text-subtle">{{ __('election.saved_at', ['time' => $assignment->checklist_completed_at->timezone($tz)->format('H:i')]) }}</p> @endif
                    </form>
                </x-ui.card>
                <x-ui.card :title="__('election.sms_fallback')">
                    <p class="text-sm text-muted">{{ __('election.sms_fallback_help') }}</p>
                    <ul class="mt-2 space-y-1 font-mono text-xs text-fg">
                        <li>ARRIVED</li><li>TURNOUT 120</li><li>INCIDENT LATE {{ __('election.sms_example_incident') }}</li><li>RESULT A=350 V=300 R=10 {{ $election->parties->take(2)->map(fn ($p) => $p->code.'=…')->implode(' ') }}</li>
                    </ul>
                </x-ui.card>
            </div>
        @elseif ($tab === 'turnout')
            <x-ui.card :title="__('election.tab_turnout')" :description="__('election.turnout_help')">
                <form wire:submit="saveTurnout" class="space-y-4">
                    <x-ui.input type="number" inputmode="numeric" min="0" wire:model="turnoutCount" :label="__('election.accredited_so_far')" required />
                    <x-ui.select wire:model="queue" :label="__('election.queue')" :placeholder="__('election.queue_unknown')" :options="['none' => __('election.queue_none'), 'short' => __('election.queue_short'), 'long' => __('election.queue_long')]" optional />
                    <x-ui.input wire:model="turnoutNote" :label="__('election.note')" optional />
                    <x-ui.button type="submit" class="w-full sm:w-auto" loading="saveTurnout">{{ __('election.send_turnout') }}</x-ui.button>
                </form>
                @if ($turnout->isNotEmpty())
                    <ol class="mt-5 divide-y divide-line border-t border-line text-sm">
                        @foreach ($turnout as $t) <li class="flex justify-between py-2"><span class="text-muted">{{ $t->observed_at->timezone($tz)->format('H:i') }}</span><span class="font-medium text-fg tabular-nums">{{ number_format($t->accredited_so_far) }}</span></li> @endforeach
                    </ol>
                @endif
            </x-ui.card>
        @elseif ($tab === 'incident')
            <x-ui.card :title="__('election.report_incident')" :description="__('election.incident_help')">
                <form wire:submit="reportIncident" class="space-y-4">
                    <x-ui.select wire:model="category" :label="__('election.category')" :placeholder="__('election.choose_category')" :options="\App\Enums\IncidentCategory::options()" required />
                    <x-ui.select wire:model="severity" :label="__('election.severity')" :options="\App\Enums\IncidentSeverity::options()" />
                    <x-ui.textarea wire:model="description" :label="__('election.what_happened')" :hint="__('election.what_happened_hint')" rows="4" required />
                    <div>
                        <label for="incident-photos" class="mb-1.5 block text-sm font-medium text-fg">{{ __('election.photos') }} <span class="font-normal text-subtle">({{ __('public.optional') }})</span></label>
                        <input id="incident-photos" type="file" wire:model="incidentPhotos" multiple accept="image/*,application/pdf" capture="environment" class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg">
                        @error('incidentPhotos.*') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-subtle">{{ __('election.evidence_help') }}</p>
                    </div>
                    <x-ui.button type="submit" variant="danger" icon="triangle-alert" class="w-full sm:w-auto" loading="reportIncident">{{ __('election.send_incident') }}</x-ui.button>
                </form>
                @if ($incidents->isNotEmpty())
                    <ul class="mt-5 divide-y divide-line border-t border-line text-sm">
                        @foreach ($incidents as $i) <li class="flex items-center justify-between gap-2 py-2"><span class="min-w-0"><span class="font-mono text-xs text-subtle">{{ $i->reference }}</span> <span class="text-fg">{{ $i->category->label() }}</span></span><x-ui.badge :tone="$i->status->tone()" size="sm">{{ $i->status->label() }}</x-ui.badge></li> @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @else
            <x-ui.card :title="__('election.result_sheet')" :description="__('election.result_help')">
                <form wire:submit="submitResult" class="space-y-4" x-data="{
                        n(v) { const x = parseInt(v, 10); return isNaN(x) ? 0 : x },
                        get sum() { return Object.values($wire.scores || {}).reduce((a, v) => a + this.n(v), 0) },
                        get problems() { const p = []; const v = this.n($wire.valid), r = this.n($wire.rejected), t = this.n($wire.totalCast), a = this.n($wire.accredited);
                            if ($wire.valid !== '' && this.sum !== v) p.push(@js(__('election.live_sum')).replace(':sum', this.sum).replace(':valid', v));
                            if ($wire.totalCast !== '' && v + r !== t) p.push(@js(__('election.live_total')));
                            if ($wire.accredited !== '' && t > a) p.push(@js(__('election.live_over')));
                            return p } }">
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input type="number" inputmode="numeric" min="0" wire:model.live.debounce.300ms="accredited" :label="__('election.accredited')" required />
                        <x-ui.input type="number" inputmode="numeric" min="0" wire:model.live.debounce.300ms="totalCast" :label="__('election.total_cast')" required />
                        <x-ui.input type="number" inputmode="numeric" min="0" wire:model.live.debounce.300ms="valid" :label="__('election.valid')" required />
                        <x-ui.input type="number" inputmode="numeric" min="0" wire:model.live.debounce.300ms="rejected" :label="__('election.rejected')" required />
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-fg">{{ __('election.votes_by_party') }}</legend>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($election->parties as $party)
                                <x-ui.input type="number" inputmode="numeric" min="0" wire:model.live.debounce.300ms="scores.{{ $party->code }}" :label="$party->code" :hint="$party->name" />
                            @endforeach
                        </div>
                        <p class="mt-2 text-sm text-muted">{{ __('election.party_total') }} <span class="font-semibold text-fg tabular-nums" x-text="sum"></span></p>
                    </fieldset>
                    <template x-if="problems.length"><x-ui.alert tone="warning"><ul class="list-disc pl-4"><template x-for="p in problems"><li x-text="p"></li></template></ul><p class="mt-1">{{ __('election.live_copy_exactly') }}</p></x-ui.alert></template>
                    <div>
                        <label for="sheet-photo" class="mb-1.5 block text-sm font-medium text-fg">{{ __('election.sheet_photo') }} <span class="text-danger">*</span></label>
                        <input id="sheet-photo" type="file" wire:model="sheetPhoto" accept="image/*,application/pdf" capture="environment" class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg">
                        @error('sheetPhoto') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-subtle">{{ __('election.sheet_photo_help') }}</p>
                    </div>
                    <x-ui.button type="submit" icon="send" class="w-full sm:w-auto" loading="submitResult">{{ __('election.send_result') }}</x-ui.button>
                </form>
                @if ($results->isNotEmpty())
                    <ul class="mt-5 divide-y divide-line border-t border-line text-sm">
                        @foreach ($results as $r)
                            <li class="py-2"><div class="flex items-center justify-between gap-2"><span class="font-mono text-xs text-subtle">{{ $r->reference }} · {{ $r->created_at->timezone($tz)->format('H:i') }}</span><x-ui.badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-ui.badge></div>
                                @foreach ($r->issues as $issue) <p class="mt-1 text-xs text-warning">{{ $issue['message'] }}</p> @endforeach</li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    @endif
</div>
