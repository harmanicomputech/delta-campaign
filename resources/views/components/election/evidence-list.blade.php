{{-- Original evidence files with their SHA-256. Opening a file is access-logged. --}}
@props(['files', 'allowed' => true])
@if ($files->isEmpty())
    <p class="text-sm text-muted">{{ __('election.no_evidence') }}</p>
@elseif (! $allowed)
    <p class="text-sm text-muted">{{ trans_choice('election.evidence_restricted', $files->count(), ['count' => $files->count()]) }}</p>
@else
    <ul class="grid grid-cols-[repeat(auto-fill,minmax(13rem,1fr))] gap-3">
        @foreach ($files as $file)
            <li class="overflow-hidden rounded-lg border border-line">
                @if ($file->isImage() && $file->mime !== 'image/heic')
                    <a href="{{ route('workspace.election.evidence', $file) }}" target="_blank" rel="noopener" class="block bg-surface-sunken">
                        <img src="{{ route('workspace.election.evidence', ['evidence' => $file, 'inline' => 1]) }}" alt="{{ __('election.evidence_image', ['name' => $file->original_name]) }}" loading="lazy" class="aspect-[4/3] w-full object-contain">
                    </a>
                @endif
                <div class="p-3 text-xs">
                    <a href="{{ route('workspace.election.evidence', $file) }}" class="flex items-center gap-1.5 font-medium text-primary hover:underline"><x-icon :name="$file->isImage() ? 'image' : 'file-text'" class="size-3.5" /> {{ $file->original_name }}</a>
                    <p class="mt-1 font-mono break-all text-subtle" title="SHA-256">SHA-256 {{ $file->sha256 }}</p>
                    <p class="mt-0.5 text-subtle">{{ $file->uploader?->name }} · {{ $file->created_at->timezone(config('campaign.display_timezone'))->format('j M, H:i') }} · {{ number_format($file->size / 1024) }} KB
                        @if ($file->integrity_ok === false) · <span class="font-semibold text-danger">{{ __('election.integrity_failed') }}</span> @elseif ($file->integrity_ok) · <span class="text-success">{{ __('election.integrity_ok') }}</span> @endif</p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
