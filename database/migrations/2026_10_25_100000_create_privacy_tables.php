<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-protection foundations: privacy-notice versions, append-only consent
 * records, the suppression list, data-subject requests, generic workflow
 * history and platform settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_notices', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('title', 200);
            $table->text('summary_en');
            $table->text('summary_pcm')->nullable();
            $table->text('body_en');
            $table->text('body_pcm')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Supporters are never hard-deleted (erasure anonymises), so this link is stable.
            $table->unsignedBigInteger('supporter_id')->nullable()->index();
            $table->char('phone_hash', 64);
            $table->string('channel', 20);
            $table->string('purpose', 30);
            $table->string('status', 20);
            $table->string('notice_version', 40)->nullable();
            $table->string('source', 20);
            $table->jsonb('evidence')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at', 6);

            $table->foreign('notice_version')->references('version')->on('privacy_notices')->restrictOnDelete();
            $table->index(['phone_hash', 'channel', 'purpose', 'recorded_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION consent_records_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'consent_records is append-only: record a new decision instead (% rejected)', TG_OP USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER consent_records_no_change BEFORE UPDATE OR DELETE ON consent_records
                FOR EACH ROW EXECUTE FUNCTION consent_records_append_only();
        SQL);

        Schema::create('suppressions', function (Blueprint $table): void {
            $table->id();
            $table->char('phone_hash', 64);
            // NULL = every channel.
            $table->string('channel', 20)->nullable();
            $table->string('reason', 30);
            $table->string('source', 20);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['phone_hash', 'lifted_at']);
        });
        DB::statement("CREATE UNIQUE INDEX suppressions_active_unique ON suppressions (phone_hash, COALESCE(channel, 'all')) WHERE lifted_at IS NULL");

        Schema::create('data_subject_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->string('type', 30);
            $table->string('status', 20)->index();
            $table->string('channel', 20);
            $table->char('phone_hash', 64)->index();
            $table->text('phone_encrypted');
            $table->unsignedBigInteger('supporter_id')->nullable()->index();
            $table->text('details')->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('verification_method', 40)->nullable();
            $table->timestamp('due_at')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('consent_records');
        DB::unprepared('DROP FUNCTION IF EXISTS consent_records_append_only();');
        Schema::dropIfExists('privacy_notices');
    }
};
