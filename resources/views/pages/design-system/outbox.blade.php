<x-layouts::app title="Developer SMS outbox" :breadcrumbs="['Design system' => route('design-system.index'), 'SMS outbox' => null]">
    <x-ui.page-header title="Developer SMS outbox" eyebrow="Local only"
                      description="SMS_DRIVER=outbox never sends. Messages appear here for 30 minutes so you can sign in locally. Never available in production." />
    <x-ui.card :padding="false">
        @forelse ($messages as $message)
            <div class="flex flex-col gap-1 border-b border-line px-5 py-4 last:border-0 sm:flex-row sm:items-start sm:gap-6">
                <div class="w-44 shrink-0 text-[0.8125rem] text-muted"><span class="font-mono">{{ $message['to'] }}</span><br>{{ \Illuminate\Support\Carbon::parse($message['at'])->timezone(config('campaign.display_timezone'))->format('H:i:s') }}</div>
                <p class="text-sm text-fg">{{ $message['message'] }}</p>
            </div>
        @empty
            <x-ui.empty-state icon="inbox" title="No messages yet" description="Request a sign-in code and it will appear here." />
        @endforelse
    </x-ui.card>
</x-layouts::app>
