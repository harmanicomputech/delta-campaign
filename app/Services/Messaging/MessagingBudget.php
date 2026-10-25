<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConsentChannel;
use App\Models\OutboundMessage;
use App\Services\Settings\Settings;
use Illuminate\Support\Carbon;

/** Monthly spending limit (setting messaging.monthly_budget, naira) and per-message cost estimates. */
final class MessagingBudget
{
    public function __construct(private readonly Settings $settings) {}

    public function limit(): float
    {
        return (float) $this->settings->get('messaging.monthly_budget');
    }

    public function spentThisMonth(): float
    {
        $start = Carbon::now((string) config('campaign.display_timezone'))->startOfMonth()->utc();

        return (float) OutboundMessage::query()->where('sent_at', '>=', $start)->sum('cost');
    }

    public function remaining(): float
    {
        return max(0.0, $this->limit() - $this->spentThisMonth());
    }

    public function unitCost(ConsentChannel $channel, int $segments): float
    {
        return $channel === ConsentChannel::Whatsapp
            ? (float) $this->settings->get('messaging.whatsapp_message_cost')
            : (float) $this->settings->get('messaging.sms_segment_cost') * max(1, $segments);
    }

    public function allows(float $amount): bool
    {
        return $this->spentThisMonth() + $amount <= $this->limit();
    }
}
