<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfficialSource;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A polling-unit result as published by INEC, recorded with its source. The
 * campaign never creates official figures; it only transcribes them.
 *
 * @property array<string, int> $scores
 * @property OfficialSource $source
 */
class OfficialResult extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['is_sample' => false];

    protected function casts(): array
    {
        return ['source' => OfficialSource::class, 'scores' => 'array', 'is_sample' => 'boolean'];
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return MorphMany<EvidenceFile, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(EvidenceFile::class, 'evidenceable');
    }
}
