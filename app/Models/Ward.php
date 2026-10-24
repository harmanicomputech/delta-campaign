<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $lga_id
 * @property int $senatorial_district_id Maintained by a database trigger.
 * @property string $code
 * @property string $name
 * @property bool $is_sample
 */
class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory;

    protected $fillable = ['lga_id', 'code', 'name', 'is_sample'];

    protected function casts(): array
    {
        return ['is_sample' => 'boolean'];
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

    /** @return HasMany<PollingUnit, $this> */
    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class);
    }
}
