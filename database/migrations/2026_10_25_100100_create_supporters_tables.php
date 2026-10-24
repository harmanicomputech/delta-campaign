<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supporters, follow-up contacts and scoped exports.
 *
 * Data minimisation: no NIN, BVN, PVC/VIN, religion, ethnicity, date of birth
 * or address is collected. An 18+ attestation is recorded instead of age.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 16)->unique();
            $table->string('full_name', 150);
            $table->text('phone_encrypted')->nullable();
            // Unique while present; cleared by erasure (the suppression list keeps opt-outs).
            $table->char('phone_hash', 64)->nullable()->unique();
            $table->char('phone_last4', 4)->nullable();
            $table->text('email_encrypted')->nullable();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('preferred_language', 5)->default('en');
            $table->string('source', 20);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_status', 20)->default('unverified');
            $table->timestamp('phone_verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->boolean('wants_to_volunteer')->default(false);
            $table->timestamp('adult_confirmed_at')->nullable();
            $table->boolean('processing_restricted')->default(false);
            $table->boolean('legal_hold')->default(false);
            $table->date('retention_until');
            $table->timestamp('erased_at')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['lga_id', 'created_at']);
            $table->index(['ward_id', 'created_at']);
            $table->index('polling_unit_id');
            $table->index(['senatorial_district_id', 'created_at']);
            $table->index('verification_status');
            $table->index(['retention_until', 'erased_at']);
        });

        DB::statement('CREATE INDEX supporters_name_trgm ON supporters USING gin (full_name gin_trgm_ops)');

        // Geography is derived from the most specific location given, so it can never be inconsistent.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION supporters_sync_geography() RETURNS trigger AS $$
            BEGIN
                IF NEW.polling_unit_id IS NOT NULL THEN
                    SELECT ward_id, lga_id, senatorial_district_id INTO NEW.ward_id, NEW.lga_id, NEW.senatorial_district_id
                    FROM polling_units WHERE id = NEW.polling_unit_id;
                ELSIF NEW.ward_id IS NOT NULL THEN
                    SELECT lga_id, senatorial_district_id INTO NEW.lga_id, NEW.senatorial_district_id FROM wards WHERE id = NEW.ward_id;
                ELSE
                    SELECT senatorial_district_id INTO NEW.senatorial_district_id FROM lgas WHERE id = NEW.lga_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER supporters_sync_geography
                BEFORE INSERT OR UPDATE OF polling_unit_id, ward_id, lga_id, senatorial_district_id ON supporters
                FOR EACH ROW EXECUTE FUNCTION supporters_sync_geography();
        SQL);

        DB::statement('ALTER TABLE supporters ADD CONSTRAINT supporters_erasure_clears_contact CHECK (erased_at IS NULL OR (phone_encrypted IS NULL AND phone_hash IS NULL AND email_encrypted IS NULL))');

        Schema::table('consent_records', function (Blueprint $table): void {
            $table->foreign('supporter_id')->references('id')->on('supporters')->restrictOnDelete();
        });
        Schema::table('data_subject_requests', function (Blueprint $table): void {
            $table->foreign('supporter_id')->references('id')->on('supporters')->nullOnDelete();
        });

        // Follow-ups now; canvassing contacts (Phase 5) use the same table.
        Schema::create('contact_attempts', function (Blueprint $table): void {
            $table->id();
            // Client-generated for idempotent offline sync.
            $table->uuid('uuid')->unique();
            $table->foreignId('supporter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('outcome', 30);
            $table->text('notes_encrypted')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('occurred_at');
            $table->string('quality_flag', 20)->default('ok');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['supporter_id', 'occurred_at']);
            $table->index(['ward_id', 'occurred_at']);
            $table->index(['lga_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['follow_up_at']);
        });

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->string('status', 20)->index();
            $table->jsonb('filters')->nullable();
            $table->boolean('include_contact')->default(false);
            $table->string('disk', 40);
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('contact_attempts');
        Schema::table('data_subject_requests', fn (Blueprint $t) => $t->dropForeign(['supporter_id']));
        Schema::table('consent_records', fn (Blueprint $t) => $t->dropForeign(['supporter_id']));
        Schema::dropIfExists('supporters');
        DB::unprepared('DROP FUNCTION IF EXISTS supporters_sync_geography();');
    }
};
