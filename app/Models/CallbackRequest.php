<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Models\Concerns\HasGeographicScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A request for a call back (USSD menu 5). The number is encrypted; staff working
 * the linked task can reveal it, and every reveal is audited.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property string|null $phone_encrypted
 * @property string $phone_hash
 * @property string $phone_last4
 * @property int $lga_id
 * @property string $topic
 * @property int|null $task_id
 */
class CallbackRequest extends Model
{
    use HasGeographicScope;

    public const array TOPICS = ['general', 'volunteering', 'problem'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open', 'is_sample' => false, 'language' => 'en'];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
            $request->reference ??= 'CB-'.strtoupper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return ['phone_encrypted' => EncryptedField::class, 'is_sample' => 'boolean'];
    }

    /** @return array{district: string|null, lga: string|null, ward: string|null, polling_unit: string|null} */
    public static function geoColumns(): array
    {
        return ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => null, 'polling_unit' => null];
    }

    public function maskedPhone(): string
    {
        return '+234 ••• ••• '.$this->phone_last4;
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
