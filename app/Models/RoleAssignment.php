<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property ScopeType $scope_type
 * @property int|null $scope_id
 * @property int|null $assigned_by
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property-read Role $role
 * @property-read User $user
 */
class RoleAssignment extends Model
{
    protected $fillable = ['user_id', 'role_id', 'scope_type', 'scope_id', 'assigned_by'];

    protected function casts(): array
    {
        return ['scope_type' => ScopeType::class, 'scope_id' => 'integer', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Human-readable scope, e.g. "Udu LGA" or "Statewide". */
    public function scopeLabel(): string
    {
        $name = match ($this->scope_type) {
            ScopeType::State => null,
            ScopeType::District => SenatorialDistrict::query()->whereKey($this->scope_id)->value('name'),
            ScopeType::Lga => Lga::query()->whereKey($this->scope_id)->value('name'),
            ScopeType::Ward => Ward::query()->whereKey($this->scope_id)->value('name'),
            ScopeType::PollingUnit => PollingUnit::query()->whereKey($this->scope_id)->value('code'),
        };

        return $name === null ? $this->scope_type->label() : "{$name} ({$this->scope_type->label()})";
    }
}
