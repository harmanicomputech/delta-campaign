<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultStatus;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Figures an agent copied from the polling-unit result sheet, with a photo.
 * This is campaign-submitted information, never an official result.
 *
 * @property int $id
 * @property ResultStatus $status
 * @property array<string, int> $scores
 * @property list<array{code: string, message: string}> $issues
 */
class ResultSubmission extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'submitted', 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(function (self $r): void {
            $r->uuid ??= (string) Str::uuid();
            $r->reference ??= 'RES-'.strtoupper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ResultStatus::class, 'scores' => 'array', 'issues' => 'array', 'reviewed_at' => 'datetime', 'is_sample' => 'boolean',
            'registered_voters' => 'integer', 'accredited_voters' => 'integer', 'valid_votes' => 'integer', 'rejected_votes' => 'integer', 'total_votes_cast' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<PollingUnit, $this> */
    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return MorphMany<EvidenceFile, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(EvidenceFile::class, 'evidenceable');
    }
}
