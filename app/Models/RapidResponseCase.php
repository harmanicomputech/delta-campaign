<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\TaskPriority;
use App\Enums\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property CaseStatus $status
 * @property bool $requires_legal
 */
class RapidResponseCase extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'new', 'priority' => 'normal', 'requires_legal' => false, 'version' => 1];

    protected static function booted(): void
    {
        static::creating(function (self $c): void {
            $c->uuid ??= (string) Str::uuid();
            $c->reference ??= 'RR-'.strtoupper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'priority' => TaskPriority::class,
            'verdict' => Verdict::class,
            'source_urls' => 'array',
            'deadline_at' => 'datetime',
            'legal_cleared_at' => 'datetime',
            'approved_at' => 'datetime',
            'requires_legal' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<RapidResponseNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(RapidResponseNote::class);
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

    /** @return BelongsTo<ContentItem, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast() && ! in_array($this->status, [CaseStatus::Approved, CaseStatus::Closed, CaseStatus::Rejected], true);
    }
}
