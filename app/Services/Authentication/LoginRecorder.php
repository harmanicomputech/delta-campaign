<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\LoginEventType;
use App\Models\Device;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Login history. Suspicious events are also logged for alerting. */
final class LoginRecorder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(LoginEventType $event, ?User $user, ?string $phoneHash = null, ?Device $device = null, array $context = []): LoginEvent
    {
        $request = request();

        $entry = LoginEvent::query()->create([
            'user_id' => $user?->id,
            'phone_hash' => $phoneHash ?? $user?->phone_hash,
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'device_id' => $device?->id,
            'context' => $context === [] ? null : $context,
        ]);

        if ($event->isSuspicious()) {
            Log::warning('Suspicious authentication event', ['event' => $event->value, 'user_id' => $user?->id, 'ip' => $request->ip()]);
        }

        return $entry;
    }
}
