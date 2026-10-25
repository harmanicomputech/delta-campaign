<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property IncidentStatus $status
 * @property IncidentSeverity $severity
 * @property IncidentCategory $category
 */
class Incident extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'reported', 'version' => 1, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(function (self $i): void {
            $i->uuid ??= (string) Str::uuid();
            $i->reference ??= 'INC-'.strtoupper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class, 'severity' => IncidentSeverity::class, 'category' => IncidentCategory::class,
            'occurred_at' => 'datetime', 'resolved_at' => 'datetime', 'version' => 'integer', 'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [IncidentStatus::Reported, IncidentStatus::Acknowledged, IncidentStatus::Escalated], true);
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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return MorphMany<EvidenceFile, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(EvidenceFile::class, 'evidenceable');
    }
}
