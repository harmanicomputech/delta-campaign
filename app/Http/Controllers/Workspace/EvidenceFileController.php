<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use App\Models\Incident;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Election\IncidentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves original evidence from private storage. Every access is audited.
 * Allowed for evidence.view in the record's area, for incident managers, and
 * for the person who uploaded it.
 */
final class EvidenceFileController extends Controller
{
    public function __invoke(Request $request, EvidenceFile $evidence, AuditLogger $audit, IncidentService $incidents): StreamedResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $subject = $evidence->evidenceable;
        abort_unless($subject instanceof Model, 404);

        $inScope = method_exists($subject, 'isWithin') && $subject->isWithin($user->geoScope('evidence.view'));
        $allowed = ($user->hasPermission('evidence.view') && $inScope)
            || ($subject instanceof Incident && $incidents->canManage($subject, $user))
            || $evidence->uploaded_by === $user->id;
        abort_unless($allowed, 403);

        $audit->record('evidence.accessed', $subject, null, ['evidence' => $evidence->uuid, 'sha256' => $evidence->sha256], $user);

        $inline = $request->boolean('inline') && in_array($evidence->mime, ['image/jpeg', 'image/png', 'image/webp'], true);
        $headers = ['Cache-Control' => 'no-store, private', 'Content-Type' => $evidence->mime, 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        return $inline
            ? Storage::disk($evidence->disk)->response($evidence->path, $evidence->original_name, $headers)
            : Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name, $headers);
    }
}
