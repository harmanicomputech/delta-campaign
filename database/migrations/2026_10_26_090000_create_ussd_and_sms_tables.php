<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Callback requests (USSD menu 5) and inbound SMS (STOP, START, HELP, REPORT).
 * Phone numbers are stored encrypted with a keyed hash for lookups; message
 * text is encrypted. Nothing here is logged in plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->text('phone_encrypted');
            $table->char('phone_hash', 64)->index();
            $table->char('phone_last4', 4);
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('senatorial_district_id')->constrained()->restrictOnDelete();
            $table->foreignId('lga_id')->constrained()->restrictOnDelete();
            $table->string('topic', 30);
            $table->string('language', 5)->default('en');
            $table->string('channel', 20);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
        });

        Schema::create('inbound_sms', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_id', 100)->nullable();
            $table->char('phone_hash', 64)->index();
            $table->char('phone_last4', 4);
            $table->text('text_encrypted');
            $table->string('keyword', 20)->nullable()->index();
            $table->string('status', 20)->index();
            $table->string('result', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_sms');
        Schema::dropIfExists('callback_requests');
    }
};
