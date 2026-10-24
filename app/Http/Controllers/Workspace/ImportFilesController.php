<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\Audit\AuditLogger;
use App\Services\Geography\PollingUnitRowValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV template (headers only) and validation error reports for geography imports. */
final class ImportFilesController extends Controller
{
    public function template(): Response
    {
        $header = implode(',', [...PollingUnitRowValidator::REQUIRED_COLUMNS, ...PollingUnitRowValidator::OPTIONAL_COLUMNS])."\n";

        return response($header, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="polling-units-template.csv"',
        ]);
    }

    public function report(Request $request, ImportJob $import, AuditLogger $audit): StreamedResponse
    {
        abort_if($import->error_report_path === null || ! Storage::disk($import->disk)->exists($import->error_report_path), 404);

        $audit->record('import.report_downloaded', $import, actor: $request->user());

        return Storage::disk($import->disk)->download($import->error_report_path, pathinfo($import->original_name, PATHINFO_FILENAME).'-issues.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
