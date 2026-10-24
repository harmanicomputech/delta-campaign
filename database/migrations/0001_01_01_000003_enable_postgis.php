<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS backs geographic storage and queries (polling-unit locations, proximity).
 * Creating the extension needs a role with CREATE privilege on the database
 * (on managed services, the provider's admin role); see docs/DEPLOYMENT.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        // Intentionally left in place: dropping PostGIS would destroy dependent geography columns.
    }
};
