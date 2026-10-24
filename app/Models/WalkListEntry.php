<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalkListEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $walk_list_id
 * @property int|null $supporter_id
 * @property string $label
 * @property int $position
 * @property WalkListEntryStatus $status
 */
class WalkListEntry extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => WalkListEntryStatus::class];
    }

    /** @return BelongsTo<WalkList, $this> */
    public function walkList(): BelongsTo
    {
        return $this->belongsTo(WalkList::class);
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }

    /** @return HasMany<ContactAttempt, $this> */
    public function contactAttempts(): HasMany
    {
        return $this->hasMany(ContactAttempt::class);
    }
}
