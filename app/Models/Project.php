<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVerification;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A public project record. Only records supplied and approved by authorised
 * staff are published; development records are marked SAMPLE.
 *
 * @property int $id
 * @property string $slug
 * @property ProjectStatus $status
 * @property string $title
 * @property string $source_note
 */
class Project extends Model
{
    use HasGeographicScope;

    public const array CATEGORIES = ['roads', 'education', 'health', 'water', 'power', 'youth', 'agriculture', 'community', 'other'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'verification' => 'unverified', 'version' => 1, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'verification' => ProjectVerification::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'published_at' => 'datetime',
            'is_sample' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return array{district: string|null, lga: string|null, ward: string|null, polling_unit: string|null} */
    public static function geoColumns(): array
    {
        return ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'ward_id', 'polling_unit' => null];
    }

    /** @param Builder<Project> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProjectStatus::Published);
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
