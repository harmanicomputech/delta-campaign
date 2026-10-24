<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, hash-chained audit log.
 *
 * hash = SHA-256(previous_hash | canonical JSON of the entry). Triggers reject
 * UPDATE, DELETE and TRUNCATE, so entries cannot be altered through the
 * application's database role. `php artisan audit:verify` re-walks the chain,
 * and daily checkpoints of the latest hash are written to the external log
 * stream (audit:checkpoint) so a rewrite by a database superuser is detectable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestampTz('occurred_at', 6);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_label', 60)->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 128)->nullable();
            $table->jsonb('changes')->nullable();
            $table->jsonb('context')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->index(['action', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'occurred_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only (% rejected)', TG_OP USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
            CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
