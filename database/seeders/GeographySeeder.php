<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Lga;
use App\Models\SenatorialDistrict;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the approved geographic hierarchy (3 districts, 25 LGAs). Idempotent:
 * re-running updates names and district membership without duplicating rows.
 */
class GeographySeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array{name: string, lgas: list<string>}> $districts */
        $districts = require database_path('data/delta-geography.php');

        DB::transaction(function () use ($districts): void {
            foreach ($districts as $code => $district) {
                $model = SenatorialDistrict::query()->updateOrCreate(['code' => $code], ['name' => $district['name']]);

                foreach ($district['lgas'] as $name) {
                    Lga::query()->updateOrCreate(
                        ['slug' => Str::slug($name)],
                        ['name' => $name, 'senatorial_district_id' => $model->id],
                    );
                }
            }
        });
    }
}
