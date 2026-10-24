<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Volunteers, training, walk lists and tasks.
 *
 * Every area-scoped table carries senatorial_district_id / lga_id / ward_id /
 * polling_unit_id, derived by one generic trigger from the most specific
 * location given, so scoping is always a single indexed predicate.
 */
return new class extends Migration
{
    private const array SCOPED = ['volunteers', 'walk_lists', 'tasks'];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_location_geography() RETURNS trigger AS $$
            BEGIN
                IF NEW.polling_unit_id IS NOT NULL THEN
                    SELECT ward_id, lga_id, senatorial_district_id INTO NEW.ward_id, NEW.lga_id, NEW.senatorial_district_id
                    FROM polling_units WHERE id = NEW.polling_unit_id;
                ELSIF NEW.ward_id IS NOT NULL THEN
                    SELECT lga_id, senatorial_district_id INTO NEW.lga_id, NEW.senatorial_district_id FROM wards WHERE id = NEW.ward_id;
                ELSIF NEW.lga_id IS NOT NULL THEN
                    SELECT senatorial_district_id INTO NEW.senatorial_district_id FROM lgas WHERE id = NEW.lga_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $geography = function (Blueprint $table, bool $required = true): void {
            $table->foreignId('senatorial_district_id')->nullable(! $required)->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->nullable(! $required)->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('polling_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('senatorial_district_id');
            $table->index('lga_id');
            $table->index('ward_id');
        };

        Schema::create('volunteers', function (Blueprint $table) use ($geography): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 150);
            $table->text('phone_encrypted');
            $table->char('phone_hash', 64)->unique();
            $table->char('phone_last4', 4);
            $geography($table);
            $table->string('status', 20)->index();
            $table->jsonb('skills')->default('[]');
            $table->jsonb('availability')->default('[]');
            $table->string('source', 20);
            $table->timestamp('applied_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('training_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('summary', 500);
            $table->text('body');
            $table->string('audience', 20)->default('all');
            $table->string('language', 5)->default('en');
            $table->unsignedTinyInteger('pass_mark')->default(70);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->jsonb('options');
            $table->unsignedTinyInteger('correct_index');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('training_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->boolean('passed');
            $table->jsonb('answers');
            $table->timestamp('completed_at');

            $table->index(['user_id', 'training_module_id']);
        });

        Schema::create('walk_lists', function (Blueprint $table) use ($geography): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $geography($table);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->date('due_on')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });

        Schema::create('walk_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('walk_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supporter_id')->nullable()->constrained()->restrictOnDelete();
            // Street or landmark description for door-to-door lists. Not a personal address record.
            $table->string('label', 200);
            $table->unsignedSmallInteger('position');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['walk_list_id', 'position']);
        });

        Schema::table('contact_attempts', function (Blueprint $table): void {
            $table->foreignId('walk_list_entry_id')->nullable()->after('supporter_id')->constrained()->nullOnDelete();
            $table->uuid('device_id')->nullable()->after('walk_list_entry_id');
            $table->boolean('recorded_offline')->default(false)->after('device_id');
        });

        Schema::create('tasks', function (Blueprint $table) use ($geography): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Optional: statewide tasks have no area and are visible only to statewide roles.
            $geography($table, false);
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_note', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->timestamp('created_at');
        });

        Schema::create('task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->jsonb('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['task_id', 'created_at']);
        });

        foreach (self::SCOPED as $name) {
            DB::unprepared("CREATE TRIGGER {$name}_sync_geography BEFORE INSERT OR UPDATE OF polling_unit_id, ward_id, lga_id, senatorial_district_id ON {$name} FOR EACH ROW EXECUTE FUNCTION sync_location_geography();");
        }

        DB::statement('CREATE INDEX volunteers_name_trgm ON volunteers USING gin (full_name gin_trgm_ops)');
        DB::statement('CREATE INDEX tasks_title_trgm ON tasks USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        foreach (['task_events', 'task_evidence', 'task_comments', 'tasks'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('contact_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('walk_list_entry_id');
            $table->dropColumn(['device_id', 'recorded_offline']);
        });
        foreach (['walk_list_entries', 'walk_lists', 'training_attempts', 'quiz_questions', 'training_modules', 'volunteers'] as $t) {
            Schema::dropIfExists($t);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS sync_location_geography();');
    }
};
