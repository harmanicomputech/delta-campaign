<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Applies config/permissions.php. Idempotent: safe to re-run after editing the
 * config; permissions removed from a role in config are detached.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $ids = [];

            foreach ((array) config('permissions.permissions') as $group => $permissions) {
                foreach ($permissions as $slug => $description) {
                    $ids[$slug] = Permission::query()->updateOrCreate(['slug' => $slug], ['group' => $group, 'description' => $description])->id;
                }
            }

            Permission::query()->whereNotIn('slug', array_keys($ids))->delete();

            foreach ((array) config('permissions.roles') as $slug => $definition) {
                $role = Role::query()->updateOrCreate(['slug' => $slug], [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'scope_level' => $definition['scope_level'],
                    'is_privileged' => (bool) $definition['privileged'],
                ]);

                $granted = $definition['permissions'] === ['*'] ? array_values($ids) : array_map(fn (string $p): int => $ids[$p] ?? throw new \LogicException("Unknown permission {$p} for role {$slug}"), $definition['permissions']);
                $role->permissions()->sync($granted);
            }
        });
    }
}
