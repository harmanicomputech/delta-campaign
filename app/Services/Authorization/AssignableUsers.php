<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds active people who may be given work in an area: they hold $permission
 * through a role assignment that covers the area (statewide, its district,
 * LGA or ward).
 */
final class AssignableUsers
{
    /**
     * @return Collection<int, User>
     */
    public function for(string $permission, ?int $districtId, ?int $lgaId, ?int $wardId, string $search = '', int $limit = 50): Collection
    {
        return User::query()->where('status', 'active')
            ->whereHas('activeAssignments', function (Builder $q) use ($permission, $districtId, $lgaId, $wardId): void {
                $q->whereHas('role.permissions', fn (Builder $p) => $p->where('slug', $permission))
                    ->where(function (Builder $w) use ($districtId, $lgaId, $wardId): void {
                        $w->where('scope_type', 'state');
                        if ($districtId !== null) {
                            $w->orWhere(fn (Builder $x) => $x->where('scope_type', 'district')->where('scope_id', $districtId));
                        }
                        if ($lgaId !== null) {
                            $w->orWhere(fn (Builder $x) => $x->where('scope_type', 'lga')->where('scope_id', $lgaId));
                        }
                        if ($wardId !== null) {
                            $w->orWhere(fn (Builder $x) => $x->where('scope_type', 'ward')->where('scope_id', $wardId));
                        }
                    });
            })
            ->when(trim($search) !== '', fn (Builder $q) => $q->where('name', 'ilike', '%'.addcslashes(trim($search), '%_\\').'%'))
            ->orderBy('name')->limit($limit)->get(['id', 'uuid', 'name', 'phone_last4', 'status']);
    }
}
