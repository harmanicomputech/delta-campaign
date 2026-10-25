<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A person assigned as polling agent for one polling unit in one election.
 *
 * @property int $id
 * @property string $status
 */
class AgentAssignment extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'assigned', 'role' => 'primary', 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['arrived_at' => 'datetime', 'checklist_completed_at' => 'datetime', 'withdrawn_at' => 'datetime', 'is_sample' => 'boolean'];
    }

    public function isActive(): bool
    {
        return $this->status === 'assigned';
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return HasMany<AgentCheckin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(AgentCheckin::class);
    }
}
