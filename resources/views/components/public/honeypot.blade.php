{{-- Hidden from people and assistive technology; bots that fill it are rejected. --}}
<div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
    <label for="website-{{ $attributes->get('id', 'hp') }}">Website</label>
    <input id="website-{{ $attributes->get('id', 'hp') }}" type="text" wire:model="website" tabindex="-1" autocomplete="off">
</div>
