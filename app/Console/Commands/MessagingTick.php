<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\MessageCampaign;
use App\Services\Messaging\CampaignService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('messaging:tick')]
#[Description('Start scheduled campaigns that are due and close finished ones')]
final class MessagingTick extends Command
{
    public function handle(CampaignService $campaigns): int
    {
        $started = 0;
        MessageCampaign::query()->where('status', CampaignStatus::Scheduled)
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->each(function (MessageCampaign $campaign) use ($campaigns, &$started): void {
                $campaigns->start($campaign);
                $started++;
            });

        MessageCampaign::query()->where('status', CampaignStatus::Sending)->each(fn (MessageCampaign $c) => $campaigns->completeIfDone($c));

        $this->info("Started {$started} campaign(s).");

        return self::SUCCESS;
    }
}
