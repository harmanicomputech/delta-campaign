<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, permissions and geographically scoped role assignments.
 * A user may hold several assignments (e.g. LGA coordinator in one LGA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            // The geographic level an assignment of this role is scoped to.
            $table->string('scope_level', 20);
            // Privileged roles require a second factor (TOTP).
            $table->boolean('is_privileged')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('group', 50);
            $table->string('description', 255);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['scope_type', 'scope_id']);
        });

        DB::statement("ALTER TABLE role_assignments ADD CONSTRAINT role_assignments_scope_valid CHECK (
            (scope_type = 'state' AND scope_id IS NULL) OR
            (scope_type IN ('district', 'lga', 'ward', 'polling_unit') AND scope_id IS NOT NULL)
        )");
        // One active assignment per user, role and scope.
        DB::statement('CREATE UNIQUE INDEX role_assignments_active_unique ON role_assignments (user_id, role_id, scope_type, COALESCE(scope_id, 0)) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
