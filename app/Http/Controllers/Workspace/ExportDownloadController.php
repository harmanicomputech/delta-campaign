<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ExportJob;
use App\Services\Exports\SupporterExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed, short-lived, requester-only download. Route requires a recent PIN confirmation. */
final class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, ExportJob $export, SupporterExportService $exports): StreamedResponse
    {
        abort_unless($export->requested_by === $request->user()?->id, 404);
        abort_unless($export->isDownloadable(), 410, 'This export has expired.');

        $exports->recordDownload($export, $request->user());

        return Storage::disk($export->disk)->download(
            (string) $export->path,
            'supporters-'.$export->created_at->format('Ymd-His').'.csv',
            ['Cache-Control' => 'no-store, private', 'Content-Type' => 'text/csv; charset=utf-8'],
        );
    }
}
