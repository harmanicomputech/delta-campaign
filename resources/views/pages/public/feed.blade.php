{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ route('public.news.index') }}</link>
        <description>{{ __('public.news_intro') }}</description>
        <language>en-ng</language>
        @foreach ($items as $item)
            <item>
                <title>{{ $item->live('title', 'en') }}</title>
                <link>{{ $item->publicUrl() }}</link>
                <guid isPermaLink="false">{{ $item->uuid }}</guid>
                <pubDate>{{ $item->published_at->toRssString() }}</pubDate>
                @if ($item->live('summary', 'en')) <description>{{ $item->live('summary', 'en') }}</description> @endif
            </item>
        @endforeach
    </channel>
</rss>
