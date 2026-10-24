<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string|null $description
 * @property TaskPriority $priority
 * @property TaskStatus $status
 * @property Carbon|null $due_at
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property int|null $senatorial_district_id
 * @property int|null $lga_id
 * @property int|null $ward_id
 * @property int|null $polling_unit_id
 * @property int $escalation_level
 * @property Carbon|null $escalated_at
 * @property Carbon|null $last_reminded_at
 * @property Carbon|null $completed_at
 * @property string|null $completion_note
 * @property int $version
 */
class Task extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'escalation_level' => 0, 'version' => 1];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'completed_at' => 'datetime',
            'escalation_level' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isClosed() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled]);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /** @return HasMany<TaskEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(TaskEvidence::class)->orderBy('created_at');
    }

    /** @return HasMany<TaskEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function areaLabel(): string
    {
        return collect([$this->ward?->name, $this->lga?->name])->filter()->implode(' · ') ?: __('tasks.statewide');
    }
}
