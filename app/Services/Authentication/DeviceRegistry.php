<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Recognises returning browsers/PWA installs by a random long-lived cookie.
 * Only a keyed hash of the cookie is stored server-side.
 */
final class DeviceRegistry
{
    /**
     * @return array{0: Device, 1: bool} The device and whether it is new to this user.
     */
    public function identify(Request $request, User $user): array
    {
        $cookieName = (string) config('campaign.auth.device_cookie');
        $token = (string) $request->cookie($cookieName);

        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            $token = Str::random(40);
        }

        // Refresh the cookie for five years (it carries no personal data).
        Cookie::queue(Cookie::make($cookieName, $token, 60 * 24 * 365 * 5, httpOnly: true, sameSite: 'lax'));

        $fingerprint = hash_hmac('sha256', $token, (string) config('app.key'));
        $device = Device::query()->firstOrNew(['user_id' => $user->id, 'fingerprint_hash' => $fingerprint]);
        $isNew = ! $device->exists || $device->revoked_at !== null;

        $device->fill([
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'last_ip' => $request->ip(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ]);
        $device->first_seen_at ??= now();
        $device->save();

        return [$device, $isNew];
    }
}
