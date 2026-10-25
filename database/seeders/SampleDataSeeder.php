<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/** Development/demo data. Every record is flagged SAMPLE. Never runs in production. */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample data must never be seeded in production.');
        }

        $this->call([GeographySeeder::class, RolesAndPermissionsSeeder::class, SampleGeographySeeder::class, PlaceholderPrivacyNoticeSeeder::class, SampleSupportersSeeder::class, SampleFieldOperationsSeeder::class, SampleMessagingSeeder::class]);
    }
}
