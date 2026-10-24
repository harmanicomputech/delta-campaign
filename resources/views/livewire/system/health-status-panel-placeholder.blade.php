<div>
    <x-ui.card :padding="false">
        <x-slot:header>
            <div class="w-full space-y-2"><x-ui.skeleton class="h-4 w-32" /><x-ui.skeleton class="h-3 w-56" /></div>
        </x-slot:header>
        <div class="divide-y divide-line" aria-busy="true" aria-label="{{ __('ui.loading') }}">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-center gap-4 px-5 py-3.5">
                    <x-ui.skeleton class="size-8 rounded-lg" />
                    <div class="flex-1 space-y-1.5"><x-ui.skeleton class="h-3 w-28" /><x-ui.skeleton class="h-2.5 w-20" /></div>
                    <x-ui.skeleton class="h-6 w-24 rounded-full" />
                </div>
            @endfor
        </div>
    </x-ui.card>
</div>
