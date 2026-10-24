<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $training_module_id
 * @property int $user_id
 * @property int $score
 * @property bool $passed
 * @property array<int, int> $answers
 * @property Carbon $completed_at
 */
class TrainingAttempt extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'passed' => 'boolean', 'score' => 'integer', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<TrainingModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
