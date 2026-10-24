<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use App\Services\Authentication\SignInService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out suspended accounts and sessions from revoked devices on their next request. */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $deviceId = $request->session()->get(SignInService::SESSION_DEVICE);
            $deviceRevoked = is_string($deviceId) && Device::query()->whereKey($deviceId)->whereNotNull('revoked_at')->exists();

            if (! $user->isActive() || $deviceRevoked) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Your session has ended.'], 401)
                    : redirect()->route('login')->with('warning', __('auth.session_ended'));
            }
        }

        return $next($request);
    }
}
