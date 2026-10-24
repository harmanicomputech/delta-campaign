<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use App\Models\ConsentRecord;
use App\Models\PrivacyNotice;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Channel- and purpose-specific consent. Decisions are append-only; the latest
 * applies. Eligibility to be contacted = latest decision is "granted" AND the
 * number is not suppressed for the channel. Checked again at send time.
 */
final class ConsentService
{
    public function __construct(private readonly SuppressionService $suppressions, private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $evidence  How the decision was captured (no secrets).
     */
    public function record(
        string $phoneHash,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
        ConsentStatus $status,
        RequestChannel $source,
        ?Supporter $supporter = null,
        array $evidence = [],
        ?User $by = null,
        ?string $noticeVersion = null,
    ): ConsentRecord {
        if ($status === ConsentStatus::Granted) {
            $noticeVersion ??= PrivacyNotice::current()?->version ?? throw new NoPublishedNotice;
        }

        return DB::transaction(function () use ($phoneHash, $channel, $purpose, $status, $source, $supporter, $evidence, $by, $noticeVersion): ConsentRecord {
            $id = DB::table('consent_records')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'supporter_id' => $supporter?->id,
                'phone_hash' => $phoneHash,
                'channel' => $channel->value,
                'purpose' => $purpose->value,
                'status' => $status->value,
                'notice_version' => $noticeVersion,
                'source' => $source->value,
                'evidence' => $evidence === [] ? null : json_encode($evidence),
                'recorded_by' => $by?->id,
                'recorded_at' => now()->format('Y-m-d H:i:s.uP'),
            ]);

            if ($status === ConsentStatus::Withdrawn) {
                $this->suppressions->suppress($phoneHash, $channel, SuppressionReason::OptOut, $source, $by);
            } else {
                // A fresh, explicit grant lifts an earlier opt-out for that channel. An erasure suppression is
                // lifted only when the person themselves comes back and consents (never by staff entry);
                // objections stay until the Data Protection Officer reviews them.
                $this->suppressions->liftOptOut($phoneHash, $channel, $by, selfService: in_array($source, [RequestChannel::Web, RequestChannel::Ussd, RequestChannel::Sms], true));
            }

            if ($supporter !== null) {
                $this->audit->record('consent.'.$status->value, $supporter, null, ['channel' => $channel->value, 'purpose' => $purpose->value, 'source' => $source->value], $by);
                // Renewed consent extends retention.
                if ($status === ConsentStatus::Granted) {
                    $supporter->forceFill(['retention_until' => now()->addMonths((int) config('campaign.privacy.retention_months'))->toDateString()])->saveQuietly();
                }
            }

            return ConsentRecord::query()->findOrFail($id);
        });
    }

    /** Withdraws every purpose on one channel (or all channels), e.g. for an SMS "STOP". */
    public function withdrawAll(string $phoneHash, ?ConsentChannel $channel, RequestChannel $source, ?Supporter $supporter = null, array $evidence = [], ?User $by = null): void
    {
        DB::transaction(function () use ($phoneHash, $channel, $source, $supporter, $evidence, $by): void {
            foreach ($channel ? [$channel] : ConsentChannel::cases() as $c) {
                foreach (ConsentPurpose::cases() as $purpose) {
                    if ($this->current($phoneHash, $c, $purpose) === ConsentStatus::Granted) {
                        $this->record($phoneHash, $c, $purpose, ConsentStatus::Withdrawn, $source, $supporter, $evidence, $by);
                    }
                }
            }

            // Suppress even when there was no recorded consent: the person asked not to be contacted.
            $this->suppressions->suppress($phoneHash, $channel, SuppressionReason::OptOut, $source, $by);
        });
    }

    public function current(string $phoneHash, ConsentChannel $channel, ConsentPurpose $purpose): ?ConsentStatus
    {
        $status = ConsentRecord::query()
            ->where('phone_hash', $phoneHash)->where('channel', $channel)->where('purpose', $purpose)
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->value('status');

        return $status instanceof ConsentStatus || $status === null ? $status : ConsentStatus::from($status);
    }

    public function isEligible(string $phoneHash, ConsentChannel $channel, ConsentPurpose $purpose): bool
    {
        return $this->current($phoneHash, $channel, $purpose) === ConsentStatus::Granted
            && ! $this->suppressions->isSuppressed($phoneHash, $channel);
    }

    /**
     * Current decision for every channel/purpose pair.
     *
     * @return array<string, array<string, ConsentStatus|null>> [channel][purpose]
     */
    public function summary(string $phoneHash): array
    {
        $latest = ConsentRecord::query()
            ->where('phone_hash', $phoneHash)
            ->orderBy('recorded_at')->orderBy('id')
            ->get(['channel', 'purpose', 'status'])
            ->reduce(function (array $carry, ConsentRecord $record): array {
                $carry[$record->channel->value][$record->purpose->value] = $record->status;

                return $carry;
            }, []);

        $summary = [];
        foreach (ConsentChannel::cases() as $channel) {
            foreach (ConsentPurpose::cases() as $purpose) {
                $summary[$channel->value][$purpose->value] = $latest[$channel->value][$purpose->value] ?? null;
            }
        }

        return $summary;
    }
}
