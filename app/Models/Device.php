<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A browser/PWA install a user has signed in from, identified by a random
 * long-lived cookie (stored here only as a hash).
 *
 * @property string $id
 * @property int $user_id
 * @property string $fingerprint_hash
 * @property string|null $name
 * @property string|null $user_agent
 * @property string|null $last_ip
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $revoked_at
 */
class Device extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['fingerprint_hash'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short description from the user agent, e.g. "Chrome on Android". */
    public function label(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $agent = (string) $this->user_agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
