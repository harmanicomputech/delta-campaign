<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\VolunteerStatus;
use App\Models\Concerns\HasGeographicScope;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int|null $supporter_id
 * @property string $full_name
 * @property string $phone_encrypted
 * @property string $phone_hash
 * @property string $phone_last4
 * @property int $senatorial_district_id
 * @property int $lga_id
 * @property int|null $ward_id
 * @property VolunteerStatus $status
 * @property list<string> $skills
 * @property list<string> $availability
 * @property string $source
 * @property Carbon $applied_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property bool $is_sample
 * @property int $version
 */
class Volunteer extends Model
{
    use HasGeographicScope;

    protected $guarded = ['id'];

    protected $hidden = ['phone_encrypted', 'phone_hash'];

    protected $attributes = ['skills' => '[]', 'availability' => '[]', 'version' => 1, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(fn (self $v) => $v->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'status' => VolunteerStatus::class,
            'phone_encrypted' => EncryptedField::class,
            'skills' => 'array',
            'availability' => 'array',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'is_sample' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function setPhone(string $input): static
    {
        $e164 = NigerianPhoneNumber::normalise($input) ?? throw new InvalidArgumentException('Invalid Nigerian mobile number.');
        $this->phone_encrypted = $e164;
        $this->phone_hash = PhoneHasher::hash($e164);
        $this->phone_last4 = substr($e164, -4);

        return $this;
    }

    public function maskedPhone(): string
    {
        return '+234 ••• ••• '.$this->phone_last4;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
