<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\RapidResponseCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams rapid-response evidence from private storage. Downloads are audited. */
final class RapidResponseEvidenceController extends Controller
{
    public function __invoke(Request $request, RapidResponseCase $case, int $note, AuditLogger $audit): StreamedResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $evidence = $case->notes()->where('kind', 'evidence')->findOrFail($note);
        $audit->record('rapid_response.evidence_downloaded', $case, null, ['sha256' => $evidence->sha256], $user);

        return Storage::disk((string) $evidence->disk)->download((string) $evidence->path, (string) $evidence->original_name, [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => (string) $evidence->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
