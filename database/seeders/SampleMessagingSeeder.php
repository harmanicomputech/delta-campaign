<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;
use RuntimeException;

/** One SAMPLE SMS template so campaigns can be tried in development. Wording must be replaced by approved text. */
class SampleMessagingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample messaging data must never be seeded in production.');
        }

        MessageTemplate::query()->firstOrCreate(['name' => 'SAMPLE: Ward meeting reminder'], [
            'channel' => 'sms',
            'purpose' => 'campaign_updates',
            'body_en' => 'Hello {first_name}, [SAMPLE] reminder: ward meeting this week in {lga}. Reply STOP to opt out.',
            'body_pcm' => 'How far {first_name}, [SAMPLE] reminder: ward meeting dey this week for {lga}. Reply STOP to stop am.',
        ]);
    }
}
