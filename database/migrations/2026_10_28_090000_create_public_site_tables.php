<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public website and CMS: content with workflow and versions, media, the
 * public project directory, events with registration and attendance, rapid
 * response cases and public contact messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 40);
            $table->string('path');
            $table->jsonb('variants')->nullable(); // width => path (webp)
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->char('sha256', 64);
            $table->string('alt_en', 250)->nullable();
            $table->string('alt_pcm', 250)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->index(); // page | news | fact_check
            $table->string('slug', 120);
            $table->string('status', 20)->index();
            $table->string('title_en', 200);
            $table->string('title_pcm', 200)->nullable();
            $table->string('summary_en', 500)->nullable();
            $table->string('summary_pcm', 500)->nullable();
            $table->text('body_en');
            $table->text('body_pcm')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('publish_at')->nullable();
            // What the public sees: a copy taken at publication, so editing the working copy never changes the live page.
            $table->jsonb('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'status', 'published_at']);
        });

        Schema::create('content_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at');

            $table->unique(['content_item_id', 'version']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 140)->unique();
            $table->string('status', 20)->index();
            $table->string('title', 200);
            $table->text('description');
            $table->string('category', 40);
            $table->foreignId('senatorial_district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('source_note', 500);
            $table->string('verification', 20)->default('unverified');
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'lga_id']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 140)->unique();
            $table->string('status', 20)->index();
            $table->string('title_en', 200);
            $table->string('title_pcm', 200)->nullable();
            $table->text('description_en');
            $table->text('description_pcm')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('venue', 200);
            $table->foreignId('senatorial_district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('registration_open')->default(true);
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 300)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supporter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 150);
            $table->text('phone_encrypted');
            $table->char('phone_hash', 64);
            $table->char('phone_last4', 4);
            // Opaque ticket code for the QR: carries no personal information.
            $table->string('ticket_token', 64)->unique();
            $table->string('status', 20)->default('registered');
            $table->string('source', 20);
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'phone_hash']);
        });

        Schema::create('rapid_response_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->string('title', 200);
            $table->text('claim');
            $table->jsonb('source_urls')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->index();
            $table->timestamp('deadline_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requires_legal')->default(false);
            $table->foreignId('legal_cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('legal_cleared_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('finding')->nullable();
            $table->string('verdict', 30)->nullable();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('rapid_response_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rapid_response_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // note | evidence | status
            $table->text('body')->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 20)->unique();
            $table->string('name', 150);
            $table->text('contact_encrypted')->nullable();
            $table->string('topic', 30);
            $table->text('message_encrypted');
            $table->foreignId('lga_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language', 5)->default('en');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_messages', 'rapid_response_notes', 'rapid_response_cases', 'event_registrations', 'events', 'projects', 'content_versions', 'content_items', 'media'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
