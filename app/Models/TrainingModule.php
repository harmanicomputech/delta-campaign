<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $summary
 * @property string $body Markdown.
 * @property string $audience all | volunteer | polling_agent
 * @property string $language
 * @property int $pass_mark
 * @property int $sort
 * @property Carbon|null $published_at
 */
class TrainingModule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'pass_mark' => 'integer', 'sort' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<TrainingAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
