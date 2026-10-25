<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ConsentChannel $channel
 * @property ConsentPurpose $purpose
 * @property array<string, mixed> $audience {scope: GeoScope array, lga_ids, ward_ids, verification, volunteers_only, language}
 * @property CampaignStatus $status
 * @property int $created_by
 * @property int|null $approved_by
 */
class MessageCampaign extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'version' => 1, 'segments' => 1];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'channel' => ConsentChannel::class,
            'purpose' => ConsentPurpose::class,
            'status' => CampaignStatus::class,
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
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

    /** @return HasMany<OutboundMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }
}
