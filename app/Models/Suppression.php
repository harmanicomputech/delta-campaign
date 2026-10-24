<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentChannel;
use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone_hash
 * @property ConsentChannel|null $channel Null = all channels.
 * @property SuppressionReason $reason
 * @property RequestChannel $source
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $lifted_at
 * @property Carbon $created_at
 */
class Suppression extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['channel' => ConsentChannel::class, 'reason' => SuppressionReason::class, 'source' => RequestChannel::class, 'lifted_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
