<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentChannel;
use App\Enums\MessageStatus;
use App\Enums\SkipReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One outbound message. The body is rendered at send time and not stored.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $message_campaign_id
 * @property int|null $supporter_id
 * @property string $phone_hash
 * @property ConsentChannel $channel
 * @property MessageStatus $status
 * @property SkipReason|null $skip_reason
 * @property int $attempts
 */
class OutboundMessage extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued', 'attempts' => 0, 'language' => 'en'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'channel' => ConsentChannel::class,
            'status' => MessageStatus::class,
            'skip_reason' => SkipReason::class,
            'attempts' => 'integer',
            'cost' => 'decimal:4',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MessageCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessageCampaign::class, 'message_campaign_id');
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }
}
