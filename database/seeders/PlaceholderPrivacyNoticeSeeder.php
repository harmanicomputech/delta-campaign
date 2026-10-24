<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Development only: a clearly marked placeholder notice so consent flows can be
 * exercised. In production the Data Protection Officer publishes the approved
 * notice; until then consent cannot be recorded (NoPublishedNotice).
 */
class PlaceholderPrivacyNoticeSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The placeholder privacy notice must never be published in production.');
        }

        PrivacyNotice::query()->firstOrCreate(['version' => 'PLACEHOLDER-2026-10'], [
            'title' => '[PLACEHOLDER] Privacy notice',
            'summary_en' => '[PLACEHOLDER: approved privacy-notice summary. Must be written and approved by the Data Protection Officer and legal counsel before launch.]',
            'summary_pcm' => '[PLACEHOLDER: approved Pidgin privacy-notice summary.]',
            'body_en' => '[PLACEHOLDER: full approved privacy notice.]',
            'body_pcm' => '[PLACEHOLDER: full approved Pidgin privacy notice.]',
        ])->forceFill(['published_at' => now()->subMinute()])->save();
    }
}
