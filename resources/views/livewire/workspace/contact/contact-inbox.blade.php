@php($tz = config('campaign.display_timezone'))
<div>
    <x-ui.page-header :title="__('contact.title')" :description="__('contact.intro')" />
    <x-ui.card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('contact.search') }}" aria-label="{{ __('contact.search') }}" class="form-control h-9 min-h-9 pl-9">
            </div>
            <select wire:model.live="status" aria-label="{{ __('supporters.status') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('supporters.all_statuses') }}</option>
                @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="topic" aria-label="{{ __('public.topic') }}" class="form-control h-9 min-h-9 sm:w-auto sm:pr-8">
                <option value="">{{ __('contact.all_topics') }}</option>
                @foreach (\App\Models\ContactMessage::TOPICS as $t) <option value="{{ $t }}">{{ __('public.topic_'.$t) }}</option> @endforeach
            </select>
        </div>
        @if ($messages->isEmpty())
            <x-ui.empty-state icon="inbox" :title="__('contact.none')" :description="__('contact.none_help')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($messages as $m)
                    <li wire:key="m-{{ $m->id }}">
                        <button type="button" wire:click="openMessage({{ $m->id }})" class="flex w-full items-center gap-4 px-5 py-3.5 text-left hover:bg-surface-sunken/60">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs text-subtle">{{ $m->reference }}</span><span class="font-medium text-fg">{{ $m->name }}</span></span>
                                <span class="block text-xs text-muted">{{ __('public.topic_'.$m->topic) }} · {{ $m->lga?->name ?? '—' }} · {{ $m->created_at->timezone($tz)->format('j M, H:i') }}</span>
                            </span>
                            <x-ui.badge :tone="$m->status->tone()" size="sm" dot>{{ $m->status->label() }}</x-ui.badge>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line">{{ $messages->links() }}</div>
        @endif
    </x-ui.card>
    <x-ui.modal name="contact-message" :title="$open ? $open->reference : __('contact.title')" icon="mail" maxWidth="2xl">
        @if ($open)
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-muted">{{ __('public.full_name') }}</dt><dd class="font-medium text-fg">{{ $open->name }}</dd></div>
                <div><dt class="text-muted">{{ __('contact.reply_to') }}</dt><dd class="font-medium break-all text-fg">{{ $open->contact_encrypted ?: __('contact.no_contact') }}</dd></div>
                <div><dt class="text-muted">{{ __('public.topic') }}</dt><dd class="text-fg">{{ __('public.topic_'.$open->topic) }}</dd></div>
                <div><dt class="text-muted">LGA</dt><dd class="text-fg">{{ $open->lga?->name ?? '—' }}</dd></div>
            </dl>
            <div class="mt-4 rounded-lg bg-surface-sunken p-4 text-sm whitespace-pre-line text-fg">{{ $open->message_encrypted }}</div>
            @if ($open->topic === 'privacy') <x-ui.alert tone="info" class="mt-4">{{ __('contact.privacy_hint') }}</x-ui.alert> @endif
            @if ($open->handler) <p class="mt-3 text-xs text-subtle">{{ __('contact.handled_by', ['name' => $open->handler->name, 'time' => $open->handled_at?->timezone($tz)->format('j M, H:i')]) }}</p> @endif
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('ui.close') }}</x-ui.button>
            @if ($open && $open->status->value !== 'spam') <x-ui.button variant="ghost" wire:click="setStatus('spam')" loading="setStatus">{{ __('contact.mark_spam') }}</x-ui.button> @endif
            @if ($open && $open->status->value !== 'handled') <x-ui.button icon="check" wire:click="setStatus('handled')" loading="setStatus">{{ __('contact.mark_handled') }}</x-ui.button> @endif
            @if ($open && $open->status->value !== 'new') <x-ui.button variant="ghost" wire:click="setStatus('new')" loading="setStatus">{{ __('contact.mark_new') }}</x-ui.button> @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
