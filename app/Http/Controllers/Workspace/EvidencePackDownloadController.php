<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\EvidencePack;
use App\Models\User;
use App\Services\Election\EvidencePackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed, PIN-confirmed download of an evidence pack by the person who requested it. */
final class EvidencePackDownloadController extends Controller
{
    public function __invoke(Request $request, EvidencePack $pack, EvidencePackService $packs): StreamedResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        abort_unless($pack->requested_by === $user->id && $user->hasPermission('evidence.export'), 404);
        abort_unless($pack->status === 'ready' && $pack->path !== null, 404);

        $packs->recordDownload($pack, $user);

        return Storage::disk((string) $pack->disk)->download((string) $pack->path, 'evidence-pack-'.substr($pack->uuid, 0, 8).'.zip', [
            'Cache-Control' => 'no-store, private',
            'X-Checksum-Sha256' => (string) $pack->sha256,
        ]);
    }
}
