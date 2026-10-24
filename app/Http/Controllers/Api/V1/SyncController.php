<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sync\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline sync for the field app. Same-origin session authentication (with CSRF),
 * so only signed-in, unlocked workspace sessions can sync.
 */
final class SyncController extends Controller
{
    public function store(Request $request, SyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:'.SyncService::MAX_ITEMS],
            'items.*' => ['array'],
        ]);

        $user = $request->user();
        assert($user instanceof User);

        $results = $sync->process($user, $data['device_id'] ?? null, array_values($data['items']));

        return response()->json([
            'results' => $results,
            'summary' => array_count_values(array_column($results, 'status')),
            'server_time' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function walkLists(Request $request, SyncService $sync): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json(['walk_lists' => $sync->walkListsFor($user), 'user' => $user->uuid, 'server_time' => now()->toIso8601String()])
            ->header('Cache-Control', 'no-store, private');
    }
}
