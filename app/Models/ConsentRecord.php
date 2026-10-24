<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One consent decision. Append-only (database trigger). The latest record per
 * phone hash, channel and purpose is the current decision.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $supporter_id
 * @property string $phone_hash
 * @property ConsentChannel $channel
 * @property ConsentPurpose $purpose
 * @property ConsentStatus $status
 * @property string|null $notice_version
 * @property RequestChannel $source
 * @property array<string, mixed>|null $evidence
 * @property int|null $recorded_by
 * @property Carbon $recorded_at
 */
class ConsentRecord extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'channel' => ConsentChannel::class,
            'purpose' => ConsentPurpose::class,
            'status' => ConsentStatus::class,
            'source' => RequestChannel::class,
            'evidence' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }
}
