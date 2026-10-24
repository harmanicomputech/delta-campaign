<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a task evidence file from private storage to people who can see the task. Downloads are audited. */
final class TaskEvidenceController extends Controller
{
    public function __invoke(Request $request, Task $task, TaskEvidence $evidence, TaskService $tasks): StreamedResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        abort_unless($evidence->task_id === $task->id && $tasks->canView($task, $user), 404);

        $tasks->recordEvidenceDownload($evidence, $user);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name, [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => $evidence->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
