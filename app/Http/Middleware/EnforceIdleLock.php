<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Authentication\SignInService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a signed-in session after a period without page activity. The person
 * re-enters their PIN (or a new one-time code) to continue; the session itself
 * is kept so unsaved context survives.
 *
 * Background Livewire requests (polling) do not count as activity, so an
 * unattended dashboard still locks.
 */
final class EnforceIdleLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        $last = (int) $session->get(SignInService::SESSION_LAST_ACTIVITY, now()->getTimestamp());
        $idleSeconds = (int) config('campaign.auth.idle_lock_minutes') * 60;

        if (now()->getTimestamp() - $last > $idleSeconds) {
            $session->put(SignInService::SESSION_LOCKED, true);
        }

        if ($session->get(SignInService::SESSION_LOCKED)) {
            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                return response()->json(['message' => 'Session locked.'], 419);
            }

            if ($request->isMethod('GET')) {
                $session->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('unlock');
        }

        if (! $request->hasHeader('X-Livewire')) {
            $session->put(SignInService::SESSION_LAST_ACTIVITY, now()->getTimestamp());
        }

        return $next($request);
    }
}
