<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\ScopeType;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\SenatorialDistrict;
use App\Models\User;
use App\Models\Ward;
use App\Services\Authorization\GeoScope;

/**
 * Area selection for role assignments and scoped records. Options are limited
 * to the actor's own scope for $scopePermission.
 */
trait PicksScope
{
    public ?int $districtId = null;

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $pollingUnitCode = '';

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    protected function actorScope(User $actor, string $permission): GeoScope
    {
        return $actor->geoScope($permission);
    }

    /** @return array<int, string> */
    protected function districtOptions(GeoScope $scope): array
    {
        return $scope->apply(SenatorialDistrict::query(), ['district' => 'id', 'lga' => null, 'ward' => null, 'polling_unit' => null])
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    protected function lgaOptions(GeoScope $scope): array
    {
        return Lga::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    protected function wardOptions(GeoScope $scope): array
    {
        if ($this->lgaId === null) {
            return [];
        }

        return Ward::query()->visibleTo($scope)->where('lga_id', $this->lgaId)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Ward $ward): array => [$ward->id => "{$ward->name} ({$ward->code})"])->all();
    }

    /** The selected target id for a scope type, or null when incomplete. */
    protected function selectedScopeId(ScopeType $type): ?int
    {
        return match ($type) {
            ScopeType::State => null,
            ScopeType::District => $this->districtId,
            ScopeType::Lga => $this->lgaId,
            ScopeType::Ward => $this->wardId,
            ScopeType::PollingUnit => PollingUnit::query()->where('code', strtoupper(trim($this->pollingUnitCode)))->value('id'),
        };
    }

    protected function resetScopePicker(): void
    {
        $this->reset('districtId', 'lgaId', 'wardId', 'pollingUnitCode');
    }
}
