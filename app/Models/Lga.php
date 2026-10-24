<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $senatorial_district_id
 * @property string $slug
 * @property string $name
 * @property string|null $inec_code
 * @property string|null $headquarters
 */
class Lga extends Model
{
    protected $fillable = ['senatorial_district_id', 'slug', 'name', 'inec_code', 'headquarters'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<SenatorialDistrict, $this> */
    public function senatorialDistrict(): BelongsTo
    {
        return $this->belongsTo(SenatorialDistrict::class);
    }

    /** @return HasMany<Ward, $this> */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }

    /** @return HasMany<PollingUnit, $this> */
    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class);
    }
}
