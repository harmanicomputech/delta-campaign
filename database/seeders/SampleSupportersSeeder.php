<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use App\Enums\SupporterSource;
use App\Enums\VerificationStatus;
use App\Models\Supporter;
use App\Models\Ward;
use App\Services\Consent\ConsentService;
use Illuminate\Database\Seeder;
use RuntimeException;

/** SAMPLE supporters ("Sample Supporter …", is_sample) for development. Never in production. */
class SampleSupportersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample supporters must never be seeded in production.');
        }

        $consent = app(ConsentService::class);
        $wards = Ward::query()->where('is_sample', true)->get();
        $sources = SupporterSource::cases();
        $statuses = [VerificationStatus::Unverified, VerificationStatus::PhoneVerified, VerificationStatus::Verified];
        mt_srand(2027);

        foreach (range(1, 240) as $i) {
            $ward = $wards[$i % $wards->count()];
            $supporter = new Supporter([
                'full_name' => sprintf('Sample Supporter %03d', $i),
                'ward_id' => $ward->id,
                'lga_id' => $ward->lga_id,
                'source' => $sources[$i % count($sources)],
            ]);
            $supporter->setPhone(sprintf('0815%07d', $i));
            $supporter->forceFill([
                'adult_confirmed_at' => now(),
                'is_sample' => true,
                'verification_status' => $statuses[$i % 3],
                'wants_to_volunteer' => $i % 7 === 0,
                'created_at' => now()->subDays(mt_rand(0, 60))->subMinutes(mt_rand(0, 1440)),
            ])->save();

            if ($i % 4 !== 0) {
                $consent->record((string) $supporter->phone_hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Staff, $supporter, ['seed' => 'sample']);
            }
            if ($i % 5 === 0) {
                $consent->record((string) $supporter->phone_hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Staff, $supporter, ['seed' => 'sample']);
            }
        }

        mt_srand();
    }
}
