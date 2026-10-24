<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (enforced by database triggers). Write through App\Services\Audit\AuditLogger only.
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon $occurred_at
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $context
 * @property string|null $previous_hash
 * @property string $hash
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'changes' => 'array', 'context' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
