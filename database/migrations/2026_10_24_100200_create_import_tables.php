<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staged imports: upload → validate (rows stored with errors) → preview →
 * confirm → apply. Every import keeps its rows, counts and error report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->string('status', 20)->index();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->char('file_hash', 64)->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->string('error_report_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('payload');
            $table->string('status', 20);
            $table->string('action', 20)->nullable();
            $table->jsonb('errors')->nullable();
            $table->jsonb('warnings')->nullable();

            $table->unique(['import_job_id', 'row_number']);
            $table->index(['import_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_jobs');
    }
};
