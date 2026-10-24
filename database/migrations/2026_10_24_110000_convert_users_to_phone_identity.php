<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users authenticate by phone number + one-time code (no passwords).
 * The number is stored encrypted, with a keyed hash for look-ups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at', 'password']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->text('phone_encrypted')->nullable()->after('name');
            $table->char('phone_hash', 64)->nullable()->unique()->after('phone_encrypted');
            $table->char('phone_last4', 4)->nullable()->after('phone_hash');
            $table->string('email')->nullable()->after('phone_last4');
            $table->string('status', 20)->default('active')->index()->after('email');
            $table->string('locale', 5)->default('en')->after('status');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('device_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', fn (Blueprint $table) => $table->dropColumn('device_id'));

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn([
                'uuid', 'phone_encrypted', 'phone_hash', 'phone_last4', 'email', 'status', 'locale',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'pin_hash',
                'pin_failed_attempts', 'pin_locked_until', 'pin_set_at', 'last_login_at', 'last_login_ip',
                'suspended_at', 'suspension_reason',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
