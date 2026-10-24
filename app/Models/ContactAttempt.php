<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\QualityFlag;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A follow-up or canvassing contact. Notes are encrypted (they may contain personal details).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $supporter_id
 * @property int $user_id
 * @property int $senatorial_district_id
 * @property int $lga_id
 * @property int|null $ward_id
 * @property int|null $polling_unit_id
 * @property ContactChannel $channel
 * @property ContactOutcome $outcome
 * @property string|null $notes_encrypted
 * @property Carbon|null $follow_up_at
 * @property Carbon $occurred_at
 * @property QualityFlag $quality_flag
 */
class ContactAttempt extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $hidden = ['notes_encrypted'];

    protected $attributes = ['quality_flag' => 'ok'];

    protected function casts(): array
    {
        return [
            'channel' => ContactChannel::class,
            'outcome' => ContactOutcome::class,
            'quality_flag' => QualityFlag::class,
            'notes_encrypted' => EncryptedField::class,
            'follow_up_at' => 'datetime',
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
