<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Authorization\GeoScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models carrying denormalised geography columns. Provides
 * Model::query()->visibleTo($user, 'permission') and $model->isWithin($scope).
 *
 * Override geoColumns() when a model's columns differ (null = not applicable).
 */
trait HasGeographicScope
{
    /**
     * @return array{district: string|null, lga: string|null, ward: string|null, polling_unit: string|null}
     */
    public static function geoColumns(): array
    {
        return ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'ward_id', 'polling_unit' => 'polling_unit_id'];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User|GeoScope $viewer, ?string $permission = null): void
    {
        $scope = $viewer instanceof User ? $viewer->geoScope($permission) : $viewer;
        $table = $this->getTable();

        $columns = array_map(fn (?string $column): ?string => $column === null ? null : "{$table}.{$column}", static::geoColumns());
        $scope->apply($query, $columns);
    }

    public function isWithin(GeoScope $scope): bool
    {
        $columns = static::geoColumns();
        $value = fn (?string $column): ?int => $column === null || $this->getAttribute($column) === null ? null : (int) $this->getAttribute($column);

        return $scope->contains($value($columns['district']), $value($columns['lga']), $value($columns['ward']), $value($columns['polling_unit']));
    }
}
