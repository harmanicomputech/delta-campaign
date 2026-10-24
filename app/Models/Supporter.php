<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\SupporterSource;
use App\Enums\VerificationStatus;
use App\Models\Concerns\HasGeographicScope;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Database\Factories\SupporterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A person who has registered support. Never hard-deleted: erasure anonymises.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property string $full_name
 * @property string|null $phone_encrypted
 * @property string|null $phone_hash
 * @property string|null $phone_last4
 * @property string|null $email_encrypted
 * @property int $senatorial_district_id Maintained by trigger.
 * @property int $lga_id
 * @property int|null $ward_id
 * @property int|null $polling_unit_id
 * @property string $preferred_language
 * @property SupporterSource $source
 * @property int|null $registered_by
 * @property VerificationStatus $verification_status
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property bool $wants_to_volunteer
 * @property Carbon|null $adult_confirmed_at
 * @property bool $processing_restricted
 * @property bool $legal_hold
 * @property Carbon $retention_until
 * @property Carbon|null $erased_at
 * @property bool $is_sample
 * @property int $version
 * @property Carbon $created_at
 */
class Supporter extends Model
{
    /** @use HasFactory<SupporterFactory> */
    use HasFactory, HasGeographicScope;

    protected $fillable = ['full_name', 'lga_id', 'ward_id', 'polling_unit_id', 'preferred_language', 'source', 'wants_to_volunteer'];

    protected $hidden = ['phone_encrypted', 'phone_hash', 'email_encrypted'];

    protected $attributes = ['verification_status' => 'unverified', 'preferred_language' => 'en', 'version' => 1, 'wants_to_volunteer' => false, 'processing_restricted' => false, 'legal_hold' => false, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(function (self $supporter): void {
            $supporter->uuid ??= (string) Str::uuid();
            $supporter->reference ??= self::newReference();
            $supporter->retention_until ??= now()->addMonths((int) config('campaign.privacy.retention_months'))->toDateString();
        });
    }

    protected function casts(): array
    {
        return [
            'source' => SupporterSource::class,
            'verification_status' => VerificationStatus::class,
            'phone_encrypted' => EncryptedField::class,
            'email_encrypted' => EncryptedField::class,
            'phone_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'adult_confirmed_at' => 'datetime',
            'erased_at' => 'datetime',
            'retention_until' => 'date',
            'wants_to_volunteer' => 'boolean',
            'processing_restricted' => 'boolean',
            'legal_hold' => 'boolean',
            'is_sample' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** Short, unambiguous public reference, e.g. SUP-7K3M9Q2X. */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $code = 'SUP-'.implode('', array_map(fn () => $alphabet[random_int(0, 31)], range(1, 8)));
        } while (static::query()->where('reference', $code)->exists());

        return $code;
    }

    public function setPhone(string $input): static
    {
        $e164 = NigerianPhoneNumber::normalise($input) ?? throw new InvalidArgumentException('Invalid Nigerian mobile number.');
        $this->phone_encrypted = $e164;
        $this->phone_hash = PhoneHasher::hash($e164);
        $this->phone_last4 = substr($e164, -4);

        return $this;
    }

    public function phone(): ?string
    {
        return $this->phone_encrypted;
    }

    public function maskedPhone(): string
    {
        return $this->phone_last4 ? '+234 ••• ••• '.$this->phone_last4 : '—';
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    public static function findByPhone(string $input): ?self
    {
        $hash = PhoneHasher::fromInput($input);

        return $hash === null ? null : static::query()->where('phone_hash', $hash)->first();
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

    /** @return BelongsTo<PollingUnit, $this> */
    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    /** @return BelongsTo<SenatorialDistrict, $this> */
    public function senatorialDistrict(): BelongsTo
    {
        return $this->belongsTo(SenatorialDistrict::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<ConsentRecord, $this> */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    /** @return HasMany<ContactAttempt, $this> */
    public function contactAttempts(): HasMany
    {
        return $this->hasMany(ContactAttempt::class);
    }

    /** @return HasMany<DataSubjectRequest, $this> */
    public function dataSubjectRequests(): HasMany
    {
        return $this->hasMany(DataSubjectRequest::class);
    }

    public function areaLabel(): string
    {
        return collect([$this->pollingUnit?->code, $this->ward?->name, $this->lga?->name])->filter()->implode(' · ');
    }
}
