<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Status history for workflow records (requests, content, cases…).
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class WorkflowTransition extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Model $subject, ?string $from, string $to, ?User $user, ?string $note = null): self
    {
        return static::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user?->id,
            'note' => $note === null ? null : mb_substr($note, 0, 1000),
        ]);
    }
}
