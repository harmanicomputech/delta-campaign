<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\ScopeType;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\RoleAssignment;
use App\Models\Ward;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The geographic area a user may act in: statewide, or a union of districts,
 * LGAs, wards and polling units. Immutable.
 *
 * Applied to queries as an OR of indexed IN-predicates on denormalised columns
 * (senatorial_district_id, lga_id, ward_id, polling_unit_id), so a broader
 * assignment automatically covers every record beneath it.
 */
final readonly class GeoScope
{
    /**
     * @param  list<int>  $districtIds
     * @param  list<int>  $lgaIds
     * @param  list<int>  $wardIds
     * @param  list<int>  $pollingUnitIds
     */
    public function __construct(
        public bool $statewide = false,
        public array $districtIds = [],
        public array $lgaIds = [],
        public array $wardIds = [],
        public array $pollingUnitIds = [],
    ) {}

    public static function statewideScope(): self
    {
        return new self(statewide: true);
    }

    public static function none(): self
    {
        return new self;
    }

    /**
     * @param  iterable<RoleAssignment>  $assignments
     */
    public static function fromAssignments(iterable $assignments): self
    {
        $ids = ['district' => [], 'lga' => [], 'ward' => [], 'polling_unit' => []];

        foreach ($assignments as $assignment) {
            if ($assignment->scope_type === ScopeType::State) {
                return self::statewideScope();
            }

            $ids[$assignment->scope_type->value][] = (int) $assignment->scope_id;
        }

        $unique = fn (array $values): array => array_values(array_unique($values));

        return new self(false, $unique($ids['district']), $unique($ids['lga']), $unique($ids['ward']), $unique($ids['polling_unit']));
    }

    public function isStatewide(): bool
    {
        return $this->statewide;
    }

    public function isEmpty(): bool
    {
        return ! $this->statewide && $this->districtIds === [] && $this->lgaIds === [] && $this->wardIds === [] && $this->pollingUnitIds === [];
    }

    /**
     * Restricts a query to rows inside this scope.
     *
     * @template TBuilder of Builder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @param  array{district?: string|null, lga?: string|null, ward?: string|null, polling_unit?: string|null}  $columns
     * @return TBuilder
     */
    public function apply(Builder|QueryBuilder $query, array $columns = []): Builder|QueryBuilder
    {
        if ($this->statewide) {
            return $query;
        }

        $columns += ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'ward_id', 'polling_unit' => 'polling_unit_id'];
        $levels = [
            'district' => $this->districtIds,
            'lga' => $this->lgaIds,
            'ward' => $this->wardIds,
            'polling_unit' => $this->pollingUnitIds,
        ];

        $applicable = array_filter($levels, fn (array $ids, string $level): bool => $ids !== [] && ($columns[$level] ?? null) !== null, ARRAY_FILTER_USE_BOTH);

        if ($applicable === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (BuilderContract $inner) use ($applicable, $columns): void {
            foreach ($applicable as $level => $ids) {
                $inner->orWhereIn($columns[$level], $ids);
            }
        });
    }

    /** Whether a location (by its ancestry) is inside this scope. */
    public function contains(?int $districtId = null, ?int $lgaId = null, ?int $wardId = null, ?int $pollingUnitId = null): bool
    {
        if ($this->statewide) {
            return true;
        }

        return ($districtId !== null && in_array($districtId, $this->districtIds, true))
            || ($lgaId !== null && in_array($lgaId, $this->lgaIds, true))
            || ($wardId !== null && in_array($wardId, $this->wardIds, true))
            || ($pollingUnitId !== null && in_array($pollingUnitId, $this->pollingUnitIds, true));
    }

    /** Whether an assignment target (scope type + id) lies entirely inside this scope. */
    public function covers(ScopeType $type, ?int $id): bool
    {
        if ($this->statewide) {
            return true;
        }

        return match ($type) {
            ScopeType::State => false,
            ScopeType::District => in_array($id, $this->districtIds, true),
            ScopeType::Lga => ($lga = Lga::query()->find($id)) !== null && $this->contains($lga->senatorial_district_id, $lga->id),
            ScopeType::Ward => ($ward = Ward::query()->find($id)) !== null && $this->contains($ward->senatorial_district_id, $ward->lga_id, $ward->id),
            ScopeType::PollingUnit => ($unit = PollingUnit::query()->find($id)) !== null
                && $this->contains($unit->senatorial_district_id, $unit->lga_id, $unit->ward_id, $unit->id),
        };
    }

    /**
     * Restricts a role_assignments query to assignments whose target lies inside this scope.
     *
     * @param  Builder<RoleAssignment>|QueryBuilder  $query
     */
    public function constrainAssignments(Builder|QueryBuilder $query): void
    {
        if ($this->statewide) {
            return;
        }

        $query->where(function (BuilderContract $q): void {
            $q->whereRaw('1 = 0');

            if ($this->districtIds !== []) {
                $q->orWhere(fn ($w) => $w->where('scope_type', ScopeType::District->value)->whereIn('scope_id', $this->districtIds));
            }

            $lgas = fn (QueryBuilder $sub) => $this->apply($sub->select('id')->from('lgas'), ['lga' => 'id', 'ward' => null, 'polling_unit' => null]);
            $wards = fn (QueryBuilder $sub) => $this->apply($sub->select('id')->from('wards'), ['ward' => 'id', 'polling_unit' => null]);
            $units = fn (QueryBuilder $sub) => $this->apply($sub->select('id')->from('polling_units'), ['polling_unit' => 'id']);

            $q->orWhere(fn ($w) => $w->where('scope_type', ScopeType::Lga->value)->whereIn('scope_id', $lgas))
                ->orWhere(fn ($w) => $w->where('scope_type', ScopeType::Ward->value)->whereIn('scope_id', $wards))
                ->orWhere(fn ($w) => $w->where('scope_type', ScopeType::PollingUnit->value)->whereIn('scope_id', $units));
        });
    }

    /** IDs of LGAs inside the scope (for filter dropdowns). @return list<int>|null null = all */
    public function lgaIdsInScope(): ?array
    {
        if ($this->statewide) {
            return null;
        }

        return array_map('intval', $this->apply(DB::table('lgas')->select('id'), ['lga' => 'id', 'ward' => null, 'polling_unit' => null])->pluck('id')->all());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'statewide' => $this->statewide,
            'district_ids' => $this->districtIds,
            'lga_ids' => $this->lgaIds,
            'ward_ids' => $this->wardIds,
            'polling_unit_ids' => $this->pollingUnitIds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['statewide'] ?? false),
            array_map('intval', $data['district_ids'] ?? []),
            array_map('intval', $data['lga_ids'] ?? []),
            array_map('intval', $data['ward_ids'] ?? []),
            array_map('intval', $data['polling_unit_ids'] ?? []),
        );
    }
}
