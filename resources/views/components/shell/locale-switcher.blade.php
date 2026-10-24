@props(['inverse' => false])
@php($locales = array_intersect_key(config('campaign.locales'), array_flip(\App\Http\Middleware\SetLocale::supported())))
<x-ui.dropdown width="w-48" :label="__('ui.language')">
    <x-slot:trigger @class(['h-9 px-2 text-[0.8125rem] font-medium sm:px-2.5', 'text-muted hover:bg-surface-sunken hover:text-fg' => ! $inverse, 'text-white/80 hover:bg-white/10 hover:text-white' => $inverse])>
        <x-icon name="languages" class="size-[1.125rem]" />
        <span class="hidden uppercase sm:inline">{{ app()->getLocale() }}</span>
    </x-slot:trigger>
    <form method="POST" action="{{ route('locale.update') }}">
        @csrf
        @foreach ($locales as $code => $locale)
            <button type="submit" name="locale" value="{{ $code }}" role="menuitemradio" aria-checked="{{ app()->getLocale() === $code ? 'true' : 'false' }}" lang="{{ $code }}"
                    class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm text-fg outline-none hover:bg-surface-sunken focus:bg-surface-sunken">
                <span class="flex-1">{{ $locale['native'] }} <span class="text-subtle">· {{ $locale['name'] }}</span></span>
                @if (app()->getLocale() === $code) <x-icon name="check" class="size-4 text-primary" /> @endif
            </button>
        @endforeach
    </form>
</x-ui.dropdown>
