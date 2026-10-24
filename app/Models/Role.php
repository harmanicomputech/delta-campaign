<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property ScopeType $scope_level
 * @property bool $is_privileged
 */
class Role extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'scope_level', 'is_privileged'];

    /** @var list<string>|null */
    private ?array $slugs = null;

    protected function casts(): array
    {
        return ['scope_level' => ScopeType::class, 'is_privileged' => 'boolean'];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        if ($this->slug === 'super_admin') {
            return ['*'];
        }

        return $this->slugs ??= $this->permissions->pluck('slug')->all();
    }

    public function grants(string $permission): bool
    {
        $slugs = $this->permissionSlugs();

        return in_array('*', $slugs, true) || in_array($permission, $slugs, true);
    }
}
