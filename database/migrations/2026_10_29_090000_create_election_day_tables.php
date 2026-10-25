<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Election-day operations. Three kinds of figure are kept apart and never
 * mixed: submitted (agent reports), verified (campaign review) and official
 * (recorded from INEC sources, with the source cited). Evidence files are
 * immutable and carry the SHA-256 of the original bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->date('election_date');
            $table->string('status', 20)->default('setup')->index(); // setup | active | closed
            $table->jsonb('checklist'); // [{key, label}]
            $table->jsonb('turnout_times')->nullable(); // ["10:00", "12:00", …]
            $table->boolean('is_sample')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('election_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12); // acronym as printed on the result sheet
            $table->string('name', 150);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['election_id', 'code']);
        });

        Schema::create('agent_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('polling_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->string('role', 10)->default('primary'); // primary | backup
            $table->string('status', 12)->default('assigned')->index(); // assigned | withdrawn
            $table->foreignId('role_assignment_id')->nullable()->constrained('role_assignments')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('checklist_completed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->index(['election_id', 'polling_unit_id']);
            $table->index(['election_id', 'lga_id']);
        });
        DB::statement("CREATE UNIQUE INDEX agent_assignments_one_active ON agent_assignments (election_id, user_id) WHERE status = 'assigned'");

        Schema::create('agent_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // arrived | checklist
            $table->jsonb('answers')->nullable(); // checklist: {key: true|false}
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable(); // from the polling unit, when both are known
            $table->string('source', 10); // web | sms | offline
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');
        });

        Schema::create('turnout_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('polling_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('accredited_so_far');
            $table->string('queue', 10)->nullable(); // none | short | long
            $table->string('note', 500)->nullable();
            $table->string('source', 10);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('observed_at');
            $table->timestamp('created_at');
            $table->index(['election_id', 'polling_unit_id', 'observed_at']);
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->string('severity', 10);
            $table->text('description');
            $table->string('status', 20)->default('reported')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('source', 10);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('occurred_at');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->index(['election_id', 'status', 'severity']);
            $table->index(['election_id', 'lga_id']);
        });

        Schema::create('result_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 10);
            $table->unsignedInteger('registered_voters')->nullable();
            $table->unsignedInteger('accredited_voters');
            $table->unsignedInteger('valid_votes');
            $table->unsignedInteger('rejected_votes');
            $table->unsignedInteger('total_votes_cast');
            $table->jsonb('scores'); // {party_code: votes}
            $table->jsonb('issues'); // arithmetic and consistency problems found at submission
            $table->string('status', 20)->default('submitted')->index(); // submitted | verified | flagged | rejected | superseded
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('result_submissions')->nullOnDelete();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->index(['election_id', 'polling_unit_id']);
            $table->index(['election_id', 'lga_id', 'status']);
        });

        Schema::create('official_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->string('source', 20); // inec_irev | inec_declaration | inec_certified_copy
            $table->string('source_reference', 500);
            $table->unsignedInteger('accredited_voters')->nullable();
            $table->unsignedInteger('valid_votes');
            $table->unsignedInteger('rejected_votes');
            $table->unsignedInteger('total_votes_cast');
            $table->jsonb('scores');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->unique(['election_id', 'polling_unit_id']);
        });

        Schema::create('evidence_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('evidenceable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('integrity_ok')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('evidence_packs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_type', 20); // polling_unit | ward | lga | state
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('status', 12)->default('queued'); // queued | building | ready | failed
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['evidence_packs', 'evidence_files', 'official_results', 'result_submissions', 'incidents', 'turnout_observations', 'agent_checkins', 'agent_assignments', 'election_parties', 'elections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
