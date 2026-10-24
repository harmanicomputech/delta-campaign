<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalkListEntryStatus;
use App\Enums\WalkListStatus;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $senatorial_district_id
 * @property int $lga_id
 * @property int|null $ward_id
 * @property int|null $polling_unit_id
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property WalkListStatus $status
 * @property Carbon|null $due_on
 * @property string|null $notes
 * @property int $version
 */
class WalkList extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'version' => 1];

    protected static function booted(): void
    {
        static::creating(fn (self $l) => $l->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['status' => WalkListStatus::class, 'due_on' => 'date', 'version' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<WalkListEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WalkListEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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

    /** @return array{done: int, total: int} */
    public function progress(): array
    {
        $counts = $this->entries()->reorder()->toBase()->selectRaw('count(*) as total, count(*) filter (where status != ?) as done', [WalkListEntryStatus::Pending->value])->first();

        return ['done' => (int) ($counts->done ?? 0), 'total' => (int) ($counts->total ?? 0)];
    }
}
