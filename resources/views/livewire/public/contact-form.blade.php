<div>
    @if ($reference)
        <div class="rounded-2xl border border-success/30 bg-success-soft p-8 text-center" role="status">
            <x-icon name="mail" class="mx-auto size-10 text-success" />
            <h2 class="mt-3 font-display text-2xl font-semibold text-fg">{{ __('public.contact_done') }}</h2>
            <p class="mt-2 text-muted">{{ __('public.contact_ref', ['ref' => $reference]) }}</p>
        </div>
    @else
        <form wire:submit="send" class="space-y-5" novalidate>
            <x-public.honeypot id="contact" />
            @error('form') <x-ui.alert tone="danger">{{ $message }}</x-ui.alert> @enderror
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="name" :label="__('public.your_name')" autocomplete="name" required />
                <x-ui.input wire:model="contact" :label="__('public.reply_to')" :hint="__('public.reply_to_hint')" optional />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model="topic" :label="__('public.topic')" :options="collect($topics)->mapWithKeys(fn ($t) => [$t => __('public.topic_'.$t)])->all()" />
                <x-ui.select wire:model="lgaId" label="LGA" :placeholder="__('public.optional')" :options="$lgas->all()" optional />
            </div>
            <x-ui.textarea wire:model="message" :label="__('public.message')" rows="6" required />
            <div>
                <x-ui.checkbox wire:model="agree" :label="__('public.contact_agree')" />
                @error('agree') <p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p> @enderror
            </div>
            <x-ui.button type="submit" size="lg" loading="send" icon="send">{{ __('public.send') }}</x-ui.button>
        </form>
    @endif
</div>
