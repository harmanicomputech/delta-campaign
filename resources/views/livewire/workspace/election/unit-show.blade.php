@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="$unit->name" :eyebrow="$unit->code" :description="collect([$unit->ward?->name, $unit->lga?->name])->filter()->implode(' · ').' · '.($unit->registered_voters !== null ? __('election.registered_voters', ['n' => number_format($unit->registered_voters)]) : __('election.registered_unknown'))" />
    @if ($election === null)
        <x-ui.card><x-ui.empty-state icon="vote" :title="__('election.none')" /></x-ui.card>
    @else
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px] [&>*]:min-w-0">
            <div class="space-y-6">
                @if ($canResults)
                    <x-ui.card :title="__('election.submissions')" :description="__('election.submissions_help')">
                        @forelse ($submissions as $s)
                            <article wire:key="sub-{{ $s->id }}" @class(['mb-4 rounded-xl border p-4 last:mb-0', 'border-primary ring-2 ring-primary/20' => $submission === $s->uuid, 'border-line' => $submission !== $s->uuid])>
                                <header class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs text-subtle">{{ $s->reference }}</span>
                                    <x-ui.badge :tone="$s->status->tone()" size="sm" dot>{{ $s->status->label() }}</x-ui.badge>
                                    <span class="text-xs text-muted">{{ $s->submitter?->name }} · {{ strtoupper($s->source) }} · {{ $s->created_at->timezone($tz)->format('j M, H:i') }}</span>
                                    @if ($s->is_sample) <x-ui.sample-badge /> @endif
                                </header>
                                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm tabular-nums">
                                        <dt class="text-muted">{{ __('election.accredited') }}</dt><dd class="text-right text-fg">{{ number_format($s->accredited_voters) }}</dd>
                                        @foreach ($s->scores as $code => $votes) <dt class="font-medium text-fg">{{ $code }}</dt><dd class="text-right text-fg">{{ number_format($votes) }}</dd> @endforeach
                                        <dt class="border-t border-line pt-1.5 text-muted">{{ __('election.valid') }}</dt><dd class="border-t border-line pt-1.5 text-right font-semibold text-fg">{{ number_format($s->valid_votes) }}</dd>
                                        <dt class="text-muted">{{ __('election.rejected') }}</dt><dd class="text-right text-fg">{{ number_format($s->rejected_votes) }}</dd>
                                        <dt class="text-muted">{{ __('election.total_cast') }}</dt><dd class="text-right text-fg">{{ number_format($s->total_votes_cast) }}</dd>
                                    </dl>
                                    <x-election.evidence-list :files="$s->evidence" :allowed="$canSeeEvidence" />
                                </div>
                                @if ($s->issues)
                                    <x-ui.alert tone="warning" class="mt-3"><ul class="list-disc pl-4">@foreach ($s->issues as $issue) <li>{{ $issue['message'] }}</li> @endforeach</ul></x-ui.alert>
                                @else
                                    <p class="mt-3 flex items-center gap-1.5 text-xs text-success"><x-icon name="circle-check" class="size-4" /> {{ __('election.arithmetic_ok') }}</p>
                                @endif
                                @if ($s->review_note) <p class="mt-2 text-xs text-muted">{{ __('election.review_note', ['name' => $s->reviewer?->name, 'note' => $s->review_note]) }}</p> @endif
                                @if ($canVerify && in_array($s->status->value, ['submitted', 'flagged'], true))
                                    <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                                        <x-ui.button size="sm" icon="check" wire:click="verify({{ $s->id }})" wire:confirm="{{ __('election.verify_confirm') }}" loading="verify">{{ __('election.verify') }}</x-ui.button>
                                        @if ($s->status->value !== 'flagged') <x-ui.button size="sm" variant="secondary" wire:click="startReview({{ $s->id }}, 'flag')">{{ __('election.flag') }}</x-ui.button> @endif
                                        <x-ui.button size="sm" variant="ghost" wire:click="startReview({{ $s->id }}, 'reject')">{{ __('election.reject') }}</x-ui.button>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-muted">{{ __('election.no_submissions') }}</p>
                        @endforelse
                    </x-ui.card>
                    <x-ui.card :title="__('election.official_result')" :description="__('election.official_help')">
                        <x-slot:actions>@if ($canOfficial) <x-ui.button size="sm" variant="secondary" icon="file-check" wire:click="startOfficial">{{ $official ? __('election.update_official') : __('election.record_official') }}</x-ui.button> @endif</x-slot:actions>
                        @if ($official)
                            <p class="text-sm text-fg"><x-ui.badge tone="info" size="sm">{{ $official->source->label() }}</x-ui.badge> <span class="break-all">{{ $official->source_reference }}</span></p>
                            <p class="mt-1 text-xs text-subtle">{{ __('election.recorded_by', ['name' => $official->recorder?->name, 'time' => $official->updated_at->timezone($tz)->format('j M, H:i')]) }}</p>
                            @if ($official->evidence->isNotEmpty()) <div class="mt-3"><x-election.evidence-list :files="$official->evidence" :allowed="$canSeeEvidence" /></div> @endif
                            <h3 class="mt-5 mb-2 text-sm font-semibold text-fg">{{ __('election.comparison') }}</h3>
                            @if ($comparison['status'] === 'no_verified')
                                <p class="text-sm text-muted">{{ __('election.compare_no_verified') }}</p>
                            @else
                                <x-ui.alert :tone="$comparison['status'] === 'matches' ? 'success' : 'danger'" class="mb-3">{{ __('election.compare_'.$comparison['status']) }}</x-ui.alert>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm tabular-nums">
                                        <thead class="text-left text-xs text-muted uppercase"><tr><th class="py-1.5">{{ __('election.field') }}</th><th class="py-1.5 text-right">{{ __('election.col_verified') }}</th><th class="py-1.5 text-right">{{ __('election.col_official') }}</th><th class="py-1.5 text-right">{{ __('election.difference') }}</th></tr></thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($comparison['rows'] as $row)
                                                <tr @class(['bg-danger-soft' => $row['difference'] !== null && $row['difference'] !== 0])>
                                                    <td class="py-1.5 font-medium text-fg">{{ in_array($row['field'], ['valid_votes', 'rejected_votes', 'total_votes_cast', 'accredited_voters'], true) ? __('election.f_'.$row['field']) : $row['field'] }}</td>
                                                    <td class="py-1.5 text-right">{{ $row['verified'] !== null ? number_format($row['verified']) : '—' }}</td>
                                                    <td class="py-1.5 text-right">{{ $row['official'] !== null ? number_format($row['official']) : '—' }}</td>
                                                    <td class="py-1.5 text-right font-semibold">{{ $row['difference'] === null ? '—' : ($row['difference'] > 0 ? '+' : '').number_format($row['difference']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <p class="text-sm text-muted">{{ __('election.no_official') }}</p>
                        @endif
                    </x-ui.card>
                @endif
                @can('incidents.view')
                    <x-ui.card :title="__('election.incidents')" :padding="false">
                        <ul class="divide-y divide-line">
                            @forelse ($incidents as $i)
                                <li><a href="{{ route('workspace.election.incidents.show', $i) }}" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-surface-sunken/60"><x-ui.badge :tone="$i->severity->tone()" size="sm">{{ $i->severity->label() }}</x-ui.badge><span class="min-w-0 flex-1 truncate text-fg">{{ $i->category->label() }} · {{ $i->description }}</span><x-ui.badge :tone="$i->status->tone()" size="sm">{{ $i->status->label() }}</x-ui.badge></a></li>
                            @empty
                                <li class="px-5 py-4 text-sm text-muted">{{ __('election.no_incidents') }}</li>
                            @endforelse
                        </ul>
                    </x-ui.card>
                @endcan
            </div>
            <div class="space-y-6">
                @can('polling-agents.view')
                    <x-ui.card :title="__('election.agents')">
                        @forelse ($agents as $a)
                            <div class="mb-3 text-sm last:mb-0">
                                <p class="font-medium text-fg">{{ $a->user->name }} <span class="font-mono text-xs text-subtle">••• {{ $a->user->phone_last4 }}</span> @if ($a->status !== 'assigned') <x-ui.badge size="sm">{{ __('election.withdrawn') }}</x-ui.badge> @elseif ($a->role === 'backup') <x-ui.badge size="sm">{{ __('election.backup') }}</x-ui.badge> @endif</p>
                                <ul class="mt-1 space-y-0.5 text-xs text-muted">
                                    @foreach ($a->checkins as $c)
                                        <li>{{ $c->occurred_at->timezone($tz)->format('H:i') }} · {{ $c->kind === 'arrived' ? __('election.arrived') : __('election.checklist_submitted', ['missing' => collect($c->answers)->filter(fn ($v) => ! $v)->count()]) }} · {{ strtoupper($c->source) }}
                                            @if ($c->distance_m !== null) · <span @class(['font-semibold text-warning' => $c->distance_m > config('campaign.election.checkin_distance_warning_m')])>{{ __('election.distance', ['m' => number_format($c->distance_m)]) }}</span> @endif</li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <p class="text-sm text-muted">{{ __('election.no_agent') }}</p>
                        @endforelse
                    </x-ui.card>
                @endcan
                <x-ui.card :title="__('election.tab_turnout')" :description="__('election.turnout_staff_help')">
                    @if ($turnout->isEmpty())
                        <p class="text-sm text-muted">{{ __('election.no_turnout') }}</p>
                    @else
                        <ol class="divide-y divide-line text-sm">
                            @foreach ($turnout as $t) <li class="flex justify-between py-1.5"><span class="text-muted">{{ $t->observed_at->timezone($tz)->format('H:i') }} · {{ strtoupper($t->source) }}</span><span class="font-medium text-fg tabular-nums">{{ number_format($t->accredited_so_far) }}</span></li> @endforeach
                        </ol>
                    @endif
                </x-ui.card>
            </div>
        </div>
        <x-ui.modal name="result-review" :title="$reviewAction === 'reject' ? __('election.reject') : __('election.flag')" icon="flag" tone="warning">
            <form wire:submit="confirmReview" id="result-review-form"><x-ui.textarea wire:model="note" :label="__('election.review_reason')" rows="3" required /></form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="result-review-form" loading="confirmReview">{{ __('ui.confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
        @if ($canOfficial)
            <x-ui.modal name="official-result" :title="__('election.record_official')" :description="__('election.official_modal_help')" icon="file-check" maxWidth="2xl">
                <form wire:submit="saveOfficial" id="official-form" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select wire:model="source" :label="__('election.source')" :options="\App\Enums\OfficialSource::options()" />
                        <x-ui.input wire:model="reference" :label="__('election.source_reference')" :hint="__('election.source_reference_hint')" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <x-ui.input type="number" min="0" wire:model="offAccredited" :label="__('election.accredited')" optional />
                        <x-ui.input type="number" min="0" wire:model="offValid" :label="__('election.valid')" required />
                        <x-ui.input type="number" min="0" wire:model="offRejected" :label="__('election.rejected')" required />
                        <x-ui.input type="number" min="0" wire:model="offTotal" :label="__('election.total_cast')" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($election->parties as $party) <x-ui.input type="number" min="0" wire:model="offScores.{{ $party->code }}" :label="$party->code" /> @endforeach
                    </div>
                    <div>
                        <label for="official-doc" class="mb-1.5 block text-sm font-medium text-fg">{{ __('election.official_document') }} <span class="font-normal text-subtle">({{ __('public.optional') }})</span></label>
                        <input id="official-doc" type="file" wire:model="document" accept="image/*,application/pdf" class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg">
                        @error('document') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" form="official-form" loading="saveOfficial">{{ __('election.save_official') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endif
</div>
