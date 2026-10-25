<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ticket_token opaque; the QR code carries only this
 * @property RegistrationStatus $status
 */
class EventRegistration extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'registered'];

    protected static function booted(): void
    {
        static::creating(function (self $r): void {
            $r->uuid ??= (string) Str::uuid();
            $r->ticket_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class, 'phone_encrypted' => EncryptedField::class, 'checked_in_at' => 'datetime'];
    }

    /** @return BelongsTo<CampaignEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CampaignEvent::class, 'event_id');
    }

    public function maskedPhone(): string
    {
        return '+234 ••• ••• '.$this->phone_last4;
    }
}
