<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Services\Authorization\GeoScope;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Staff account. Signs in with phone + one-time code; privileged roles add TOTP.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $phone Decrypted E.164 number (virtual; stored encrypted).
 * @property string|null $phone_hash
 * @property string|null $phone_last4
 * @property string|null $email
 * @property UserStatus $status
 * @property string $locale
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property string|null $pin_hash
 * @property int $pin_failed_attempts
 * @property \Illuminate\Support\Carbon|null $pin_locked_until
 * @property \Illuminate\Support\Carbon|null $pin_set_at
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property string|null $suspension_reason
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'locale'];

    /** Database defaults, mirrored so new models are complete before a refresh. */
    protected $attributes = ['status' => 'active', 'locale' => 'en', 'pin_failed_attempts' => 0];

    protected $hidden = ['phone_encrypted', 'phone_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'pin_hash', 'remember_token'];

    /** @var array<string, bool>|null */
    private ?array $permissionCache = null;

    private ?GeoScope $scopeCache = null;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'phone_encrypted' => EncryptedField::class,
            'two_factor_secret' => EncryptedField::class,
            'two_factor_recovery_codes' => EncryptedField::class.':json',
            'two_factor_confirmed_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'pin_set_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'pin_failed_attempts' => 'integer',
        ];
    }

    /** Accounts have no password: sign-in is phone + one-time code (+ TOTP for privileged roles). */
    public function getAuthPassword(): string
    {
        return '';
    }

    /** "Remember me" is not offered: sessions expire and idle sessions lock. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->phone_encrypted;
    }

    /** Sets the encrypted number, its keyed hash and last four digits from any accepted format. */
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
        return $this->phone_last4 ? '+234 ••• ••• '.$this->phone_last4 : '—';
    }

    public static function findByPhone(string $input): ?self
    {
        $hash = PhoneHasher::fromInput($input);

        return $hash === null ? null : static::query()->where('phone_hash', $hash)->first();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function activeAssignments(): HasMany
    {
        return $this->roleAssignments()->whereNull('revoked_at');
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /** @return HasMany<LoginEvent, $this> */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /** @return Collection<int, RoleAssignment> */
    public function loadedAssignments(): Collection
    {
        if (! $this->relationLoaded('activeAssignments')) {
            $this->load('activeAssignments.role.permissions');
        }

        return $this->getRelation('activeAssignments');
    }

    public function hasRole(string $slug): bool
    {
        return $this->loadedAssignments()->contains(fn (RoleAssignment $a): bool => $a->role->slug === $slug);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = [];
            foreach ($this->loadedAssignments() as $assignment) {
                foreach ($assignment->role->permissionSlugs() as $slug) {
                    $this->permissionCache[$slug] = true;
                }
            }
        }

        return isset($this->permissionCache['*']) || isset($this->permissionCache[$permission]);
    }

    /** Scope of all active assignments that grant $permission (or all assignments when null). */
    public function geoScope(?string $permission = null): GeoScope
    {
        if ($permission === null && $this->scopeCache !== null) {
            return $this->scopeCache;
        }

        $assignments = $this->loadedAssignments()->filter(
            fn (RoleAssignment $a): bool => $permission === null || $a->role->grants($permission),
        );

        $scope = GeoScope::fromAssignments($assignments);

        return $permission === null ? ($this->scopeCache = $scope) : $scope;
    }

    public function requiresTwoFactor(): bool
    {
        return $this->loadedAssignments()->contains(fn (RoleAssignment $a): bool => $a->role->is_privileged);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Broadest scope type held, for display. */
    public function broadestScope(): ?ScopeType
    {
        return $this->loadedAssignments()->map(fn (RoleAssignment $a): ScopeType => $a->scope_type)->sortBy(fn (ScopeType $t): int => $t->rank())->first();
    }

    public function flushAuthorizationCache(): void
    {
        $this->permissionCache = null;
        $this->scopeCache = null;
        $this->unsetRelation('activeAssignments');
    }

    /** @param Builder<self> $query */
    public function scopeVisibleTo(Builder $query, self $viewer, string $permission = 'users.view'): void
    {
        $scope = $viewer->geoScope($permission);

        if ($scope->isStatewide()) {
            return;
        }

        // Users whose assignments all fall inside the viewer's scope.
        $query->whereHas('activeAssignments', fn (Builder $q) => $scope->constrainAssignments($q))
            ->whereDoesntHave('activeAssignments', fn (Builder $q) => $q->whereNot(fn (Builder $inner) => $scope->constrainAssignments($inner)));
    }
}
