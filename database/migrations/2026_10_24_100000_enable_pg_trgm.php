<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pg_trgm powers indexed fuzzy/substring search (names, places) without
 * loading large tables into memory. It is a trusted extension from PostgreSQL 13.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Left in place: dependent trigram indexes would be dropped with it.
    }
};
