<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inbound SMS. The sender is stored as a keyed hash (plus last four digits)
 * and the text encrypted, so neither appears in plaintext in the database or logs.
 *
 * @property int $id
 * @property string $phone_hash
 * @property string|null $text_encrypted
 * @property string|null $keyword
 * @property string $status received | processed | ignored | failed
 */
class InboundSms extends Model
{
    protected $table = 'inbound_sms';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['text_encrypted' => EncryptedField::class, 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
