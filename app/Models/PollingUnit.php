<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGeographicScope;
use Database\Factories\PollingUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ward_id
 * @property int $lga_id Maintained by a database trigger.
 * @property int $senatorial_district_id Maintained by a database trigger.
 * @property string $code
 * @property string $name
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $registered_voters Only from official reference data.
 * @property bool $is_sample
 */
class PollingUnit extends Model
{
    /** @use HasFactory<PollingUnitFactory> */
    use HasFactory;

    use HasGeographicScope;

    public static function geoColumns(): array
    {
        return ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'ward_id', 'polling_unit' => 'id'];
    }

    protected $fillable = ['ward_id', 'code', 'name', 'latitude', 'longitude', 'registered_voters', 'is_sample', 'import_job_id'];

    /** The generated PostGIS column is never written or serialised directly. */
    protected $hidden = ['location'];

    protected function casts(): array
    {
        return [
            'is_sample' => 'boolean',
            'registered_voters' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
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

    /** @return BelongsTo<SenatorialDistrict, $this> */
    public function senatorialDistrict(): BelongsTo
    {
        return $this->belongsTo(SenatorialDistrict::class);
    }

    /** @return HasMany<AgentAssignment, $this> */
    public function agentAssignments(): HasMany
    {
        return $this->hasMany(AgentAssignment::class);
    }

    /**
     * Polling units within $metres of a point, nearest first.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, int $metres): void
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        $query->whereNotNull('location')
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$longitude, $latitude, $metres])
            ->orderByRaw("location <-> {$point}", [$longitude, $latitude]);
    }

    /** Distance in metres from this polling unit to a point, or null without coordinates. */
    public function distanceTo(float $latitude, float $longitude): ?float
    {
        $value = static::query()->whereKey($this->getKey())
            ->selectRaw('ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as metres', [$longitude, $latitude])
            ->value('metres');

        return $value === null ? null : (float) $value;
    }
}
