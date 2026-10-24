<form wire:submit="submit" novalidate class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.input wire:model.blur="name" label="Full name" autocomplete="name" required />
        <x-ui.phone-input wire:model.blur="phone" />
    </div>
    <x-ui.select wire:model.live="area" label="Area" placeholder="Choose an area" :options="$this->areas()" required hint="Options are scoped to the user's assigned geography in real modules." />
    <x-ui.textarea wire:model.blur="notes" label="Notes" optional rows="3" hint="Maximum 500 characters." />

    <fieldset class="space-y-3">
        <legend class="text-[0.8125rem] font-medium text-fg">Channels <span class="font-normal text-subtle">(consent is recorded per channel)</span></legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-line p-3.5"><x-ui.checkbox wire:model="channels" value="sms" label="SMS" description="Campaign updates by text message." /></div>
            <div class="rounded-lg border border-line p-3.5"><x-ui.checkbox wire:model="channels" value="whatsapp" label="WhatsApp" description="Approved template messages only." /></div>
        </div>
    </fieldset>

    <div>
        <x-ui.checkbox wire:model.live="acknowledged" label="I understand this is a sample form" description="Required to submit. Demonstrates an accepted-terms rule." />
        @error('acknowledged') <p class="mt-1.5 flex items-center gap-1.5 text-[0.8125rem] text-danger"><x-icon name="circle-alert" class="size-3.5" />{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-line pt-5">
        <x-ui.button variant="ghost" wire:click="resetForm">Reset</x-ui.button>
        <x-ui.button type="submit" loading="submit" icon="check">Validate</x-ui.button>
    </div>
</form>
