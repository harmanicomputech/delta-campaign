<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only. Sample data is seeded explicitly outside production:
     * php artisan db:seed --class=SampleDataSeeder
     */
    public function run(): void
    {
        $this->call(GeographySeeder::class);
    }
}
