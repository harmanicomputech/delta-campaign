<?php

declare(strict_types=1);

namespace App\Services\Election;

use App\Models\EvidenceFile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores original evidence untouched on private storage, with the SHA-256 of
 * the bytes as received. Files are never re-encoded (that would change the
 * hash); integrity can be re-checked at any time.
 */
final class EvidenceStore
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(UploadedFile $file, Model $subject, ?User $by): EvidenceFile
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, (array) config('campaign.election.evidence_mimes'), true)) {
            throw ValidationException::withMessages(['evidence' => __('election.evidence_type')]);
        }
        if ($file->getSize() > (int) config('campaign.election.evidence_max_kb') * 1024) {
            throw ValidationException::withMessages(['evidence' => __('election.evidence_size', ['mb' => (int) (config('campaign.election.evidence_max_kb') / 1024)])]);
        }

        $disk = (string) config('campaign.election.evidence_disk');
        $hash = (string) hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('evidence/'.now()->format('Y/m/d'), Str::uuid().'.'.($file->guessExtension() ?: 'bin'), $disk);

        $evidence = new EvidenceFile([
            'uploaded_by' => $by?->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()) ?: 'evidence', 0, 255),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'sha256' => $hash,
            'created_at' => now(),
        ]);
        $evidence->evidenceable()->associate($subject);
        $evidence->save();

        $this->audit->as($by ?? 'system', fn () => $this->audit->record('evidence.stored', $subject, null, ['evidence' => $evidence->uuid, 'sha256' => $hash]));

        return $evidence;
    }

    /** Re-hashes the stored file and records whether it still matches. */
    public function verify(EvidenceFile $evidence): bool
    {
        $storage = Storage::disk($evidence->disk);
        $ok = $storage->exists($evidence->path) && hash_equals($evidence->sha256, hash('sha256', (string) $storage->get($evidence->path)));
        $evidence->forceFill(['verified_at' => now(), 'integrity_ok' => $ok])->save();

        if (! $ok) {
            $this->audit->as('system', fn () => $this->audit->record('evidence.integrity_failed', $evidence, null, ['sha256' => $evidence->sha256]));
        }

        return $ok;
    }
}
