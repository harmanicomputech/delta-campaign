<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\LoginEventType;
use App\Jobs\SendTransactionalSms;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Completes sign-in and manages sessions (listing, revocation, sign-out). */
final class SignInService
{
    public const string SESSION_DEVICE = 'auth.device_id';

    public const string SESSION_LAST_ACTIVITY = 'auth.last_activity';

    public const string SESSION_PIN_CONFIRMED = 'auth.pin_confirmed_at';

    public const string SESSION_LOCKED = 'auth.locked';

    public const string SESSION_PENDING = 'auth.pending_two_factor';

    public function __construct(
        private readonly DeviceRegistry $devices,
        private readonly LoginRecorder $recorder,
        private readonly AuditLogger $audit,
    ) {}

    public function complete(Request $request, User $user): Device
    {
        [$device, $isNew] = $this->devices->identify($request, $user);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget([self::SESSION_PENDING, self::SESSION_LOCKED]);
        $request->session()->put([
            self::SESSION_DEVICE => $device->id,
            self::SESSION_LAST_ACTIVITY => now()->getTimestamp(),
            // A fresh sign-in proves possession of the phone; treat it as a recent confirmation.
            self::SESSION_PIN_CONFIRMED => now()->getTimestamp(),
        ]);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $this->recorder->record(LoginEventType::LoginSucceeded, $user, device: $device);
        $this->audit->record('auth.login', $user, context: ['device' => $device->label(), 'new_device' => $isNew], actor: $user);

        if ($isNew) {
            $this->recorder->record(LoginEventType::NewDevice, $user, device: $device);

            // Do not alert on the very first sign-in to an account.
            if ($user->devices()->count() > 1 && $user->phone) {
                $when = now()->timezone(config('campaign.display_timezone'))->format('j M H:i');
                SendTransactionalSms::dispatch(
                    $user->phone,
                    'New sign-in to your '.config('app.name')." account ({$device->label()}, {$when}). If this was not you, contact your administrator immediately.",
                    'security.new_device',
                );
            }
        }

        return $device;
    }

    public function signOut(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->recorder->record(LoginEventType::Logout, $user);
            $this->audit->record('auth.logout', $user, actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** @return list<object{id: string, device_id: string|null, ip_address: string|null, user_agent: string|null, last_activity: int}> */
    public function sessions(User $user): array
    {
        return DB::table('sessions')->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'device_id', 'ip_address', 'user_agent', 'last_activity'])
            ->all();
    }

    public function revokeSession(User $user, string $sessionId, ?User $by = null): bool
    {
        $deleted = DB::table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete() > 0;

        if ($deleted) {
            $this->recorder->record(LoginEventType::SessionRevoked, $user, context: ['by' => $by?->id]);
            $this->audit->record('auth.session_revoked', $user, actor: $by);
        }

        return $deleted;
    }

    /** Ends every session of a user, optionally keeping the current one. */
    public function revokeAllSessions(User $user, ?string $exceptSessionId = null, ?User $by = null): int
    {
        $count = DB::table('sessions')->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        if ($count > 0) {
            $this->recorder->record(LoginEventType::SessionRevoked, $user, context: ['count' => $count, 'by' => $by?->id]);
            $this->audit->record('auth.sessions_revoked', $user, context: ['count' => $count], actor: $by);
        }

        return $count;
    }

    public function revokeDevice(Device $device, ?User $by = null): void
    {
        $device->forceFill(['revoked_at' => now()])->save();
        DB::table('sessions')->where('device_id', $device->id)->delete();
        $this->audit->record('auth.device_revoked', $device->user, context: ['device' => $device->label()], actor: $by);
    }
}
