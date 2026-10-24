<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $training_module_id
 * @property string $prompt
 * @property list<string> $options
 * @property int $correct_index
 * @property int $sort
 */
class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['correct_index'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_index' => 'integer'];
    }

    /** @return BelongsTo<TrainingModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }
}
