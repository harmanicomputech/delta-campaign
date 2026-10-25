<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communications engine: templates, campaigns (with two-person approval) and
 * one row per outbound message. Message bodies are rendered at send time and
 * not stored; recipients are referenced by supporter and keyed phone hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('channel', 20);
            $table->string('purpose', 30);
            $table->text('body_en');
            $table->text('body_pcm')->nullable();
            // WhatsApp business-initiated messages may only use provider-approved templates.
            $table->string('provider_template', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('message_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->foreignId('message_template_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('purpose', 30);
            $table->jsonb('audience');
            $table->string('status', 20)->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('estimated_recipients')->default(0);
            $table->unsignedSmallInteger('segments')->default(1);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('pause_reason', 200)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('outbound_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('message_campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->char('phone_hash', 64)->index();
            $table->char('phone_last4', 4);
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('language', 5)->default('en');
            $table->string('status', 20);
            $table->string('skip_reason', 30)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 120)->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 120)->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['message_campaign_id', 'status']);
            $table->unique(['message_campaign_id', 'supporter_id']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('message_campaigns');
        Schema::dropIfExists('message_templates');
    }
};
