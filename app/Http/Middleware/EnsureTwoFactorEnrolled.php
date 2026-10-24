<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Holders of privileged roles must enrol an authenticator app before using the workspace. */
final class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Two-factor authentication must be set up first.'], 403)
                : redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
