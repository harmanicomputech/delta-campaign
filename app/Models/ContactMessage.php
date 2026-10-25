<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A message from the public contact form. Contact details and text are encrypted. */
class ContactMessage extends Model
{
    public const array TOPICS = ['general', 'volunteering', 'media', 'report', 'privacy'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'new', 'language' => 'en'];

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            $m->uuid ??= (string) Str::uuid();
            $m->reference ??= 'MSG-'.strtoupper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return ['status' => ContactStatus::class, 'contact_encrypted' => EncryptedField::class, 'message_encrypted' => EncryptedField::class, 'handled_at' => 'datetime'];
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
