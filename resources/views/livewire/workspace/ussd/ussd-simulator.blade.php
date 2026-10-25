<div>
    <x-ui.page-header :title="__('simulator.title')" :description="__('simulator.intro')" />

    <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)] [&>*]:min-w-0">
        <div class="mx-auto w-full max-w-[340px]">
            <div class="rounded-[2.25rem] border border-line-strong bg-ink-900 p-3 shadow-xl dark:bg-black">
                <div class="mx-auto mb-3 h-1.5 w-16 rounded-full bg-white/20"></div>
                <div class="flex min-h-[23rem] flex-col rounded-[1.5rem] bg-surface-sunken p-4 text-fg">
                    @if ($screen === null)
                        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                            <x-icon name="smartphone" class="size-8 opacity-60" />
                            <p class="text-sm opacity-80">{{ __('simulator.dial_help') }}</p>
                        </div>
                    @else
                        <div class="flex-1 rounded-xl bg-surface p-3 font-mono text-[0.8125rem] leading-5 whitespace-pre-line shadow-inner" role="status" aria-live="polite">{{ $screen }}</div>
                        <p class="mt-1.5 text-right font-mono text-[0.6875rem] {{ mb_strlen($screen) > $max ? 'text-danger' : 'opacity-60' }}">{{ mb_strlen($screen) }}/{{ $max }}</p>
                        @unless ($ended)
                            <form wire:submit="send" class="mt-2 flex gap-2">
                                <label for="ussd-input" class="sr-only">{{ __('simulator.reply') }}</label>
                                <input id="ussd-input" wire:model="input" autocomplete="off" inputmode="text" maxlength="100" autofocus class="form-control flex-1 font-mono" placeholder="{{ __('simulator.reply') }}">
                                <x-ui.button type="submit" loading="send">{{ __('simulator.send') }}</x-ui.button>
                            </form>
                        @else
                            <p class="mt-2 text-center text-xs font-medium opacity-70">{{ __('simulator.ended') }}</p>
                        @endunless
                    @endif
                </div>
                <div class="mt-3 flex gap-2">
                    <x-ui.button class="flex-1" icon="phone" wire:click="dial" loading="dial">{{ $screen === null ? __('simulator.dial') : __('simulator.redial') }}</x-ui.button>
                    @if ($screen !== null)
                        <x-ui.button variant="danger" wire:click="hangUp">{{ __('simulator.hang_up') }}</x-ui.button>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('simulator.settings')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.phone-input wire:model="phone" name="phone" :label="__('simulator.caller')" />
                    <div class="sm:pt-7">
                        @if ($canSave)
                            <x-ui.checkbox wire:model.live="practice" :label="__('simulator.practice')" :description="__('simulator.practice_help')" />
                        @else
                            <x-ui.alert tone="info">{{ __('simulator.practice_only') }}</x-ui.alert>
                        @endif
                    </div>
                </div>
                @unless ($practice)
                    <x-ui.alert tone="warning" class="mt-4">{{ __('simulator.saving_warning') }}</x-ui.alert>
                @endunless
            </x-ui.card>

            <x-ui.card :title="__('simulator.transcript')" :padding="false">
                @if ($log === [])
                    <x-ui.empty-state compact icon="smartphone" :title="__('simulator.no_transcript')" />
                @else
                    <ol class="divide-y divide-line">
                        @foreach ($log as $i => $step)
                            <li class="flex gap-4 px-5 py-3 text-sm" wire:key="log-{{ $i }}">
                                <span class="w-20 shrink-0 font-mono text-xs text-subtle">{{ $step['input'] }}</span>
                                <span class="min-w-0 flex-1 font-mono text-xs whitespace-pre-line text-fg">{{ $step['screen'] }}</span>
                                <x-ui.badge :tone="$step['length'] > $max ? 'danger' : 'neutral'" size="sm">{{ $step['length'] }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('simulator.shortcuts')">
                <p class="text-sm text-muted">{{ __('simulator.shortcuts_help') }}</p>
            </x-ui.card>
        </div>
    </div>
</div>
