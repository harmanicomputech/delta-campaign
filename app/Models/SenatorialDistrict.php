<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 */
class SenatorialDistrict extends Model
{
    protected $fillable = ['code', 'name'];

    /** @return HasMany<Lga, $this> */
    public function lgas(): HasMany
    {
        return $this->hasMany(Lga::class);
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
