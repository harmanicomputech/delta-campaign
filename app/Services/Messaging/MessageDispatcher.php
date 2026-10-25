<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\CampaignStatus;
use App\Enums\ConsentChannel;
use App\Enums\MessageStatus;
use App\Enums\SkipReason;
use App\Models\OutboundMessage;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SuppressionService;
use App\Services\Messaging\Contracts\WhatsAppGateway;
use App\Services\Settings\Settings;
use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends one queued message, re-checking everything immediately before sending:
 * campaign state, quiet hours, the person's record, consent and suppression
 * (so an opt-out received after queueing still stops the message), the monthly
 * budget, provider health and the provider rate limit.
 *
 * Returns null when the message reached a final state, or the number of
 * seconds after which the job should try again.
 */
final class MessageDispatcher
{
    public const int MAX_ATTEMPTS = 3;

    /** Delays between attempts after temporary failures (seconds). */
    public const array BACKOFF = [60, 300, 900];

    public function __construct(
        private readonly ConsentService $consent,
        private readonly SuppressionService $suppressions,
        private readonly MessageRenderer $renderer,
        private readonly MessagingBudget $budget,
        private readonly QuietHours $quietHours,
        private readonly ProviderHealth $health,
        private readonly CampaignService $campaigns,
        private readonly Settings $settings,
        private readonly SmsGateway $sms,
        private readonly WhatsAppGateway $whatsapp,
    ) {}

    public function dispatch(OutboundMessage $message): ?int
    {
        $message->refresh()->loadMissing(['campaign.template', 'supporter']);

        if ($message->status !== MessageStatus::Queued) {
            return null;
        }

        $campaign = $message->campaign;

        if ($campaign !== null) {
            if ($campaign->status === CampaignStatus::Cancelled) {
                $message->forceFill(['status' => MessageStatus::Cancelled])->save();

                return null;
            }
            if ($campaign->status !== CampaignStatus::Sending) {
                return 300; // Paused (or not started): look again later.
            }
        }

        if (($wait = $this->quietHours->secondsUntilOpen()) > 0) {
            return $wait;
        }

        if ($reason = $this->ineligible($message)) {
            $message->forceFill(['status' => MessageStatus::Skipped, 'skip_reason' => $reason])->save();
            $this->finish($message);

            return null;
        }

        $unit = $this->budget->unitCost($message->channel, $campaign?->segments ?? 1);
        if (! $this->budget->allows($unit)) {
            if ($campaign !== null) {
                $this->campaigns->pause($campaign, null, __('messaging.paused_budget'));
            }

            return 300;
        }

        if (($tripped = $this->health->trippedFor($message->channel)) > 0) {
            return $tripped;
        }

        $perSecond = (int) $this->settings->get('messaging.rate_per_second');
        if (! RateLimiter::attempt('messaging-rate:'.$message->channel->value, $perSecond, fn () => true, 1)) {
            return 1;
        }

        $result = $this->send($message);
        $message->attempts++;

        if ($result->accepted) {
            $cost = $result->cost !== null && (float) $result->cost > 0 ? (float) $result->cost : $unit;
            DB::transaction(function () use ($message, $result, $cost, $campaign): void {
                $message->forceFill([
                    'status' => MessageStatus::Sent,
                    'provider' => $message->channel === ConsentChannel::Whatsapp ? $this->whatsapp->name() : $this->sms->name(),
                    'provider_message_id' => $result->providerMessageId,
                    'cost' => $cost,
                    'sent_at' => now(),
                    'last_error' => null,
                ])->save();
                $campaign?->increment('actual_cost', $cost);
            });
            $this->health->success($message->channel);
            $this->finish($message);

            return null;
        }

        $this->health->failure($message->channel, (string) $result->status);
        $message->last_error = mb_substr((string) $result->status, 0, 120);

        if ($result->permanentFailure || $message->attempts >= self::MAX_ATTEMPTS) {
            $message->forceFill(['status' => MessageStatus::Failed, 'failed_at' => now()])->save();
            $this->finish($message);

            return null;
        }

        $message->save();

        return self::BACKOFF[$message->attempts - 1] ?? 900;
    }

    private function ineligible(OutboundMessage $message): ?SkipReason
    {
        $supporter = $message->supporter;
        $purpose = $message->campaign?->purpose;

        return match (true) {
            $supporter === null || $supporter->erased_at !== null => SkipReason::Erased,
            (bool) $supporter->processing_restricted => SkipReason::Restricted,
            $supporter->phone() === null => SkipReason::NoPhone,
            $this->suppressions->isSuppressed($message->phone_hash, $message->channel) => SkipReason::Suppressed,
            $purpose !== null && ! $this->consent->isEligible($message->phone_hash, $message->channel, $purpose) => SkipReason::NoConsent,
            default => null,
        };
    }

    private function send(OutboundMessage $message): SmsResult
    {
        $template = $message->campaign->template;
        $variables = $this->renderer->variables($message->supporter);
        $phone = (string) $message->supporter->phone();

        if ($message->channel === ConsentChannel::Whatsapp) {
            $parameters = array_map(fn (string $name): string => $variables[$name] ?? '', $this->renderer->placeholders($template->body_en));

            return $this->whatsapp->sendTemplate($phone, (string) $template->provider_template, $message->language, $parameters);
        }

        return $this->sms->send($phone, $this->renderer->render($template->body($message->language), $variables));
    }

    private function finish(OutboundMessage $message): void
    {
        if ($message->campaign !== null) {
            $this->campaigns->completeIfDone($message->campaign);
        }
    }
}
