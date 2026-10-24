<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Lga;
use App\Models\SenatorialDistrict;
use App\Models\Ward;
use App\Services\Authorization\GeoScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate field metrics by area, always restricted to a viewer's scope.
 * Only counts leave this class, never individual records.
 *
 * Levels: district → lga → ward. A breakdown lists the areas at one level
 * (optionally inside a parent area) with their counts; the summary is the
 * sum of that breakdown, so the two always agree.
 */
final class AreaMetrics
{
    public const array LEVELS = [
        'district' => ['model' => SenatorialDistrict::class, 'key' => 'senatorial_district_id', 'parent' => null, 'columns' => ['district' => 'id', 'lga' => null, 'ward' => null, 'polling_unit' => null]],
        'lga' => ['model' => Lga::class, 'key' => 'lga_id', 'parent' => 'senatorial_district_id', 'columns' => ['district' => 'senatorial_district_id', 'lga' => 'id', 'ward' => null, 'polling_unit' => null]],
        'ward' => ['model' => Ward::class, 'key' => 'ward_id', 'parent' => 'lga_id', 'columns' => ['district' => 'senatorial_district_id', 'lga' => 'lga_id', 'ward' => 'id', 'polling_unit' => null]],
    ];

    public const array METRICS = ['supporters', 'verified', 'new_supporters', 'volunteers', 'applications', 'contacts', 'reached', 'open_tasks', 'overdue_tasks', 'wards', 'covered_wards'];

    /** Seconds aggregates are cached; the command centre refreshes on this cadence. */
    private const int TTL = 60;

    /** The level a scope naturally starts at. */
    public function startLevel(GeoScope $scope): string
    {
        return match (true) {
            $scope->statewide => 'district',
            $scope->districtIds !== [] => 'lga',
            default => 'ward',
        };
    }

    /** The child level of a level, or null at the bottom. */
    public function childLevel(string $level): ?string
    {
        return ['district' => 'lga', 'lga' => 'ward', 'ward' => null][$level];
    }

    /**
     * Areas at $level inside the scope (and inside $parentId when given).
     *
     * @return Collection<int, string> id => name
     */
    public function areas(GeoScope $scope, string $level, ?int $parentId = null): Collection
    {
        $definition = self::LEVELS[$level];
        $query = $definition['model']::query()->orderBy('name');
        $scope->apply($query, $definition['columns']);

        if ($parentId !== null && $definition['parent'] !== null) {
            $query->where($definition['parent'], $parentId);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * @return Collection<int, array<string, int|string>> one row per area: id, name and every metric
     */
    public function breakdown(GeoScope $scope, string $level, ?int $parentId = null): Collection
    {
        $areas = $this->areas($scope, $level, $parentId);

        if ($areas->isEmpty()) {
            return collect();
        }

        $cacheKey = 'metrics:'.md5(json_encode([$scope->toArray(), $level, $parentId, $areas->keys()->all()]));

        $counts = Cache::remember($cacheKey, self::TTL, fn (): array => $this->count($scope, self::LEVELS[$level]['key'], $areas->keys()->all()));

        return $areas->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name]
            + array_combine(self::METRICS, array_map(fn (string $metric): int => (int) ($counts[$metric][$id] ?? 0), self::METRICS)))->values();
    }

    /**
     * @param  Collection<int, array<string, int|string>>  $breakdown
     * @return array<string, int>
     */
    public function summary(Collection $breakdown): array
    {
        return array_combine(self::METRICS, array_map(fn (string $metric): int => (int) $breakdown->sum($metric), self::METRICS));
    }

    /**
     * Daily supporter registrations and field contacts for the last $days days (display time zone).
     *
     * @param  list<int>  $areaIds  restrict to these areas at $level (null = whole scope)
     * @return array{labels: list<string>, supporters: list<int>, contacts: list<int>}
     */
    public function trend(GeoScope $scope, string $level, ?array $areaIds, int $days = 30): array
    {
        $tz = (string) config('campaign.display_timezone');
        $from = Carbon::now($tz)->startOfDay()->subDays($days - 1);
        $key = self::LEVELS[$level]['key'];

        $daily = function (string $table, string $column, ?callable $extra = null) use ($scope, $key, $areaIds, $from, $tz): array {
            $query = DB::table($table)->where($column, '>=', $from->copy()->utc())
                ->selectRaw("to_char(({$column} at time zone 'UTC') at time zone ?, 'YYYY-MM-DD') as day, count(*) as n", [$tz])
                ->groupBy('day');
            $scope->apply($query, ['district' => "{$table}.senatorial_district_id", 'lga' => "{$table}.lga_id", 'ward' => "{$table}.ward_id", 'polling_unit' => "{$table}.polling_unit_id"]);
            if ($areaIds !== null) {
                $query->whereIn("{$table}.{$key}", $areaIds);
            }
            if ($extra) {
                $extra($query);
            }

            return $query->pluck('n', 'day')->map(fn ($n) => (int) $n)->all();
        };

        $cacheKey = 'trend:'.md5(json_encode([$scope->toArray(), $level, $areaIds, $days, $from->toDateString()]));
        [$supporters, $contacts] = Cache::remember($cacheKey, self::TTL, fn (): array => [
            $daily('supporters', 'created_at', fn (Builder $q) => $q->whereNull('erased_at')),
            $daily('contact_attempts', 'occurred_at'),
        ]);

        $labels = [];
        for ($d = $from->copy(); $d->lte(Carbon::now($tz)); $d->addDay()) {
            $labels[] = $d->toDateString();
        }

        return [
            'labels' => $labels,
            'supporters' => array_map(fn (string $day): int => $supporters[$day] ?? 0, $labels),
            'contacts' => array_map(fn (string $day): int => $contacts[$day] ?? 0, $labels),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, array<int, int>> metric => [area id => count]
     */
    private function count(GeoScope $scope, string $key, array $ids): array
    {
        $grouped = function (string $table, string $select, ?callable $where = null) use ($scope, $key, $ids): Collection {
            $query = DB::table($table)->whereIn("{$table}.{$key}", $ids)->groupBy("{$table}.{$key}")
                ->selectRaw("{$table}.{$key} as area, {$select}");
            $scope->apply($query, $table === 'wards'
                ? ['district' => 'wards.senatorial_district_id', 'lga' => 'wards.lga_id', 'ward' => 'wards.id', 'polling_unit' => null]
                : ['district' => "{$table}.senatorial_district_id", 'lga' => "{$table}.lga_id", 'ward' => "{$table}.ward_id", 'polling_unit' => "{$table}.polling_unit_id"]);
            if ($where) {
                $where($query);
            }

            return $query->get()->keyBy('area');
        };

        $weekAgo = now()->subDays(7);
        $supporters = $grouped('supporters', 'count(*) as total, count(*) filter (where verification_status = \'verified\') as verified, count(*) filter (where created_at >= ?) as recent', fn (Builder $q) => $q->whereNull('erased_at')->addBinding($weekAgo, 'select'));
        $volunteers = $grouped('volunteers', "count(*) filter (where status = 'approved') as active, count(*) filter (where status = 'applied') as applied, count(distinct ward_id) filter (where status = 'approved') as covered");
        $contacts = $grouped('contact_attempts', "count(*) as total, count(*) filter (where outcome in ('reached', 'registered')) as reached", fn (Builder $q) => $q->where('occurred_at', '>=', $weekAgo));
        $tasks = $grouped('tasks', "count(*) filter (where status not in ('completed', 'cancelled')) as open, count(*) filter (where status not in ('completed', 'cancelled') and due_at < now()) as overdue");

        // Wards are counted from the wards table itself; at ward level each area is one ward.
        $wards = $key === 'ward_id'
            ? collect($ids)->mapWithKeys(fn (int $id) => [$id => (object) ['n' => 1]])
            : DB::table('wards')->whereIn($key === 'lga_id' ? 'lga_id' : 'senatorial_district_id', $ids)
                ->groupBy($key === 'lga_id' ? 'lga_id' : 'senatorial_district_id')
                ->selectRaw(($key === 'lga_id' ? 'lga_id' : 'senatorial_district_id').' as area, count(*) as n')->get()->keyBy('area');

        $pluck = fn (Collection $rows, string $field): array => $rows->map(fn ($r) => (int) $r->{$field})->all();

        return [
            'supporters' => $pluck($supporters, 'total'),
            'verified' => $pluck($supporters, 'verified'),
            'new_supporters' => $pluck($supporters, 'recent'),
            'volunteers' => $pluck($volunteers, 'active'),
            'applications' => $pluck($volunteers, 'applied'),
            'covered_wards' => $pluck($volunteers, 'covered'),
            'contacts' => $pluck($contacts, 'total'),
            'reached' => $pluck($contacts, 'reached'),
            'open_tasks' => $pluck($tasks, 'open'),
            'overdue_tasks' => $pluck($tasks, 'overdue'),
            'wards' => $pluck($wards, 'n'),
        ];
    }
}
