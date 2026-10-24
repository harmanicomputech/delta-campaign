<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Geographic hierarchy: senatorial district → LGA → ward → polling unit.
 *
 * Polling units carry denormalised lga_id and senatorial_district_id so that
 * geographic scoping is a single indexed predicate. A trigger keeps them
 * consistent with the parent ward, so they can never drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('senatorial_districts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('lgas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->string('slug', 60)->unique();
            $table->string('name', 100)->unique();
            // Official code from INEC reference data, when supplied. Never invented.
            $table->string('inec_code', 20)->nullable()->unique();
            $table->string('headquarters', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->unique(['lga_id', 'code']);
            $table->index(['lga_id', 'name']);
        });

        Schema::create('polling_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 255);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('registered_voters')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->foreignUuid('import_job_id')->nullable();
            $table->timestamps();

            $table->index(['ward_id', 'name']);
            $table->index('lga_id');
            $table->index('senatorial_district_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE polling_units
                ADD COLUMN location geography(Point, 4326)
                    GENERATED ALWAYS AS (
                        CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                             THEN ST_SetSRID(ST_MakePoint(longitude::float8, latitude::float8), 4326)::geography
                        END
                    ) STORED,
                ADD CONSTRAINT polling_units_coordinates_pair CHECK ((latitude IS NULL) = (longitude IS NULL)),
                ADD CONSTRAINT polling_units_latitude_range CHECK (latitude BETWEEN -90 AND 90),
                ADD CONSTRAINT polling_units_longitude_range CHECK (longitude BETWEEN -180 AND 180)
        SQL);
        DB::statement('CREATE INDEX polling_units_location_gist ON polling_units USING gist (location)');
        DB::statement('CREATE INDEX polling_units_name_trgm ON polling_units USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX wards_name_trgm ON wards USING gin (name gin_trgm_ops)');

        // Keep denormalised geography consistent with the parent record.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION wards_sync_geography() RETURNS trigger AS $$
            BEGIN
                SELECT senatorial_district_id INTO NEW.senatorial_district_id FROM lgas WHERE id = NEW.lga_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER wards_sync_geography
                BEFORE INSERT OR UPDATE OF lga_id, senatorial_district_id ON wards
                FOR EACH ROW EXECUTE FUNCTION wards_sync_geography();

            CREATE OR REPLACE FUNCTION polling_units_sync_geography() RETURNS trigger AS $$
            BEGIN
                SELECT lga_id, senatorial_district_id INTO NEW.lga_id, NEW.senatorial_district_id
                FROM wards WHERE id = NEW.ward_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER polling_units_sync_geography
                BEFORE INSERT OR UPDATE OF ward_id, lga_id, senatorial_district_id ON polling_units
                FOR EACH ROW EXECUTE FUNCTION polling_units_sync_geography();

            -- Moving a ward to another LGA carries its polling units with it.
            CREATE OR REPLACE FUNCTION wards_cascade_geography() RETURNS trigger AS $$
            BEGIN
                IF NEW.lga_id IS DISTINCT FROM OLD.lga_id OR NEW.senatorial_district_id IS DISTINCT FROM OLD.senatorial_district_id THEN
                    UPDATE polling_units SET lga_id = NEW.lga_id, senatorial_district_id = NEW.senatorial_district_id WHERE ward_id = NEW.id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER wards_cascade_geography
                AFTER UPDATE OF lga_id, senatorial_district_id ON wards
                FOR EACH ROW EXECUTE FUNCTION wards_cascade_geography();

            -- Moving an LGA to another district carries its wards and polling units with it.
            CREATE OR REPLACE FUNCTION lgas_cascade_district() RETURNS trigger AS $$
            BEGIN
                IF NEW.senatorial_district_id IS DISTINCT FROM OLD.senatorial_district_id THEN
                    UPDATE wards SET senatorial_district_id = NEW.senatorial_district_id WHERE lga_id = NEW.id;
                    UPDATE polling_units SET senatorial_district_id = NEW.senatorial_district_id WHERE lga_id = NEW.id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER lgas_cascade_district
                AFTER UPDATE OF senatorial_district_id ON lgas
                FOR EACH ROW EXECUTE FUNCTION lgas_cascade_district();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_units');
        Schema::dropIfExists('wards');
        DB::unprepared('DROP FUNCTION IF EXISTS polling_units_sync_geography(); DROP FUNCTION IF EXISTS wards_sync_geography(); DROP FUNCTION IF EXISTS wards_cascade_geography();');
        DB::unprepared('DROP TRIGGER IF EXISTS lgas_cascade_district ON lgas; DROP FUNCTION IF EXISTS lgas_cascade_district();');
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('senatorial_districts');
    }
};
