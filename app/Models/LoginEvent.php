<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $phone_hash
 * @property LoginEventType $event
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $device_id
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event' => LoginEventType::class, 'context' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
