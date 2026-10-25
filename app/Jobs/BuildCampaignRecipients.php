<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\MessageCampaign;
use App\Models\OutboundMessage;
use App\Models\Supporter;
use App\Services\Messaging\AudienceBuilder;
use App\Services\Messaging\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates one queued message per person in the audience (idempotent: the
 * campaign + supporter pair is unique) and queues each for sending. Eligibility
 * is not decided here; it is checked at send time.
 */
final class BuildCampaignRecipients implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('messaging');
    }

    public function handle(AudienceBuilder $audience): void
    {
        $campaign = MessageCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $audience->query($campaign->audience)->select(['id', 'phone_hash', 'phone_last4', 'lga_id', 'preferred_language'])
            ->chunkById(500, function ($supporters) use ($campaign): void {
                $now = now();
                $rows = $supporters->map(fn (Supporter $s): array => [
                    'uuid' => (string) Str::uuid(),
                    'message_campaign_id' => $campaign->id,
                    'supporter_id' => $s->id,
                    'phone_hash' => $s->phone_hash,
                    'phone_last4' => (string) $s->phone_last4,
                    'lga_id' => $s->lga_id,
                    'channel' => $campaign->channel->value,
                    'language' => $s->preferred_language === 'pcm' ? 'pcm' : 'en',
                    'status' => 'queued',
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('outbound_messages')->insertOrIgnore($rows);

                OutboundMessage::query()->where('message_campaign_id', $campaign->id)->whereIn('supporter_id', $supporters->pluck('id'))
                    ->where('status', 'queued')->where('attempts', 0)->pluck('id')
                    ->each(fn (int $id) => SendOutboundMessage::dispatch($id));
            });

        app(CampaignService::class)->completeIfDone($campaign->refresh());
    }
}
