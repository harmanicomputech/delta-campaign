<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ConsentChannel;
use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * Numbers that must not be contacted. Keyed by phone hash so opt-outs survive
 * erasure of the supporter record. A channel of null blocks every channel.
 */
final class SuppressionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function suppress(string $phoneHash, ?ConsentChannel $channel, SuppressionReason $reason, RequestChannel $source, ?User $by = null, ?string $note = null): Suppression
    {
        $existing = $this->active($phoneHash, $channel);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $suppression = Suppression::query()->create([
                'phone_hash' => $phoneHash,
                'channel' => $channel,
                'reason' => $reason,
                'source' => $source,
                'note' => $note,
                'created_by' => $by?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->active($phoneHash, $channel) ?? throw new \RuntimeException('Suppression race could not be resolved.');
        }

        $this->forget($phoneHash);
        $this->audit->record('suppression.added', $suppression, null, ['channel' => $channel?->value ?? 'all', 'reason' => $reason->value, 'source' => $source->value], $by);

        return $suppression;
    }

    public function isSuppressed(string $phoneHash, ConsentChannel $channel): bool
    {
        // Short cache: sends check this for every message; writes clear it.
        $channels = Cache::remember("suppression:{$phoneHash}", 60, fn (): array => Suppression::query()
            ->where('phone_hash', $phoneHash)->whereNull('lifted_at')->get(['channel'])
            ->map(fn (Suppression $s): string => $s->channel?->value ?? 'all')->all());

        return in_array('all', $channels, true) || in_array($channel->value, $channels, true);
    }

    /**
     * Lifts opt-outs after a new explicit consent. With $selfService (the person
     * consented on their own via web, USSD or SMS) an earlier erasure suppression
     * is lifted too. Objection and administrative suppressions always stay.
     */
    public function liftOptOut(string $phoneHash, ConsentChannel $channel, ?User $by = null, bool $selfService = false): void
    {
        $reasons = $selfService ? [SuppressionReason::OptOut, SuppressionReason::Erasure] : [SuppressionReason::OptOut];

        $lifted = Suppression::query()->where('phone_hash', $phoneHash)->whereNull('lifted_at')
            ->whereIn('reason', $reasons)
            ->where(fn ($q) => $q->where('channel', $channel)->orWhereNull('channel'))
            ->update(['lifted_at' => now(), 'lifted_by' => $by?->id]);

        if ($lifted > 0) {
            $this->forget($phoneHash);
            $this->audit->record('suppression.lifted_by_consent', 'suppression', null, ['channel' => $channel->value], $by);
        }
    }

    /** Manual lift by the Data Protection Officer (audited, reason required). */
    public function lift(Suppression $suppression, User $by, string $reason): void
    {
        $suppression->forceFill(['lifted_at' => now(), 'lifted_by' => $by->id])->save();
        $this->forget($suppression->phone_hash);
        $this->audit->record('suppression.lifted', $suppression, null, ['reason' => $reason], $by);
    }

    private function active(string $phoneHash, ?ConsentChannel $channel): ?Suppression
    {
        return Suppression::query()->where('phone_hash', $phoneHash)->whereNull('lifted_at')
            ->where(fn ($q) => $channel ? $q->where('channel', $channel) : $q->whereNull('channel'))
            ->first();
    }

    private function forget(string $phoneHash): void
    {
        Cache::forget("suppression:{$phoneHash}");
    }
}
