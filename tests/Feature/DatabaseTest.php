<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_on_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_postgis_is_enabled_by_migrations(): void
    {
        $this->assertNotEmpty(DB::scalar('select postgis_lib_version()'));

        // Geography distance between two points ~1.1 km apart.
        $metres = DB::scalar("select ST_Distance('SRID=4326;POINT(5.75 5.52)'::geography, 'SRID=4326;POINT(5.76 5.52)'::geography)");
        $this->assertEqualsWithDelta(1108, $metres, 15);
    }

    public function test_session_table_exists_for_revocable_database_sessions(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('sessions'));
    }
}
