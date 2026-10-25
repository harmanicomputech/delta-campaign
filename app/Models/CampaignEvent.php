<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\HasGeographicScope;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A campaign event (named to avoid confusion with Laravel's event system).
 *
 * @property int $id
 * @property string $slug
 * @property EventStatus $status
 * @property int|null $capacity
 * @property bool $registration_open
 */
class CampaignEvent extends Model
{
    use HasGeographicScope, HasTranslations;

    protected $table = 'events';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'registration_open' => true, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'registration_open' => 'boolean',
            'capacity' => 'integer',
            'is_sample' => 'boolean',
        ];
    }

    /** @return array{district: string|null, lga: string|null, ward: string|null, polling_unit: string|null} */
    public static function geoColumns(): array
    {
        return ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'ward_id', 'polling_unit' => null];
    }

    /** @param Builder<CampaignEvent> $query */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', [EventStatus::Published, EventStatus::Cancelled, EventStatus::Completed])->whereNotNull('published_at');
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function spacesLeft(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->registrations()->where('status', '!=', 'cancelled')->count());
    }

    public function acceptsRegistrations(): bool
    {
        return $this->status === EventStatus::Published && $this->registration_open && $this->starts_at->isFuture() && $this->spacesLeft() !== 0;
    }
}
