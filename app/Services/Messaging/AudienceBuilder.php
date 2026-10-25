<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Models\Supporter;
use App\Services\Authorization\GeoScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supporters a campaign is aimed at, always inside the creator's scope. Consent
 * and suppression are counted here for the estimate, and checked again for every
 * message immediately before it is sent.
 */
final class AudienceBuilder
{
    /**
     * @param  array<string, mixed>  $audience  {scope, lga_ids, ward_ids, verification, volunteers_only, language}
     * @return Builder<Supporter>
     */
    public function query(array $audience): Builder
    {
        $scope = GeoScope::fromArray((array) ($audience['scope'] ?? []));

        return Supporter::query()->visibleTo($scope)
            ->whereNull('erased_at')->where('processing_restricted', false)->whereNotNull('phone_hash')
            ->when(! empty($audience['lga_ids']), fn (Builder $q) => $q->whereIn('lga_id', array_map('intval', $audience['lga_ids'])))
            ->when(! empty($audience['ward_ids']), fn (Builder $q) => $q->whereIn('ward_id', array_map('intval', $audience['ward_ids'])))
            ->when(! empty($audience['verification']), fn (Builder $q) => $q->whereIn('verification_status', $audience['verification']))
            ->when(! empty($audience['volunteers_only']), fn (Builder $q) => $q->where('wants_to_volunteer', true))
            ->when(in_array($audience['language'] ?? '', ['en', 'pcm'], true), fn (Builder $q) => $q->where('preferred_language', $audience['language']));
    }

    /**
     * @param  Builder<Supporter>  $query
     * @return Builder<Supporter>
     */
    public function eligible(Builder $query, ConsentChannel $channel, ConsentPurpose $purpose): Builder
    {
        return $query
            ->whereRaw("(select c.status from consent_records c where c.phone_hash = supporters.phone_hash and c.channel = ? and c.purpose = ? order by c.recorded_at desc, c.id desc limit 1) = 'granted'", [$channel->value, $purpose->value])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('suppressions as s')
                ->whereColumn('s.phone_hash', 'supporters.phone_hash')->whereNull('s.lifted_at')
                ->where(fn ($w) => $w->whereNull('s.channel')->orWhere('s.channel', $channel->value)));
    }

    /**
     * @param  array<string, mixed>  $audience
     * @return array{total: int, eligible: int}
     */
    public function counts(array $audience, ConsentChannel $channel, ConsentPurpose $purpose): array
    {
        return [
            'total' => $this->query($audience)->count(),
            'eligible' => $this->eligible($this->query($audience), $channel, $purpose)->count(),
        ];
    }
}
