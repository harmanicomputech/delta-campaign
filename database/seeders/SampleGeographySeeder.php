<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * SAMPLE wards and polling units for development and demos. Every record is
 * flagged is_sample and coded "SMP-…" so it can never be mistaken for official
 * INEC reference data. Refuses to run in production.
 */
class SampleGeographySeeder extends Seeder
{
    public const int WARDS_PER_LGA = 3;

    public const int UNITS_PER_WARD = 4;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample geography must never be seeded in production.');
        }

        mt_srand(2027);

        foreach (Lga::query()->orderBy('name')->get() as $lgaIndex => $lga) {
            for ($w = 1; $w <= self::WARDS_PER_LGA; $w++) {
                $ward = Ward::query()->updateOrCreate(
                    ['lga_id' => $lga->id, 'code' => sprintf('SMP-W%02d', $w)],
                    ['name' => sprintf('Sample Ward %02d', $w), 'is_sample' => true],
                );

                for ($p = 1; $p <= self::UNITS_PER_WARD; $p++) {
                    PollingUnit::query()->updateOrCreate(
                        ['code' => sprintf('SMP-%02d-%02d-%03d', $lgaIndex + 1, $w, $p)],
                        [
                            'ward_id' => $ward->id,
                            'name' => sprintf('Sample Polling Unit %02d/%02d/%03d', $lgaIndex + 1, $w, $p),
                            // Synthetic coordinates inside the state's bounding box.
                            'latitude' => round(5.0 + mt_rand(0, 150_000) / 100_000, 6),
                            'longitude' => round(5.2 + mt_rand(0, 150_000) / 100_000, 6),
                            'is_sample' => true,
                        ],
                    );
                }
            }
        }

        mt_srand();
    }
}
