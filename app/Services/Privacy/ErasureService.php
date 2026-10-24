<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use App\Models\ContactAttempt;
use App\Models\Supporter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\SuppressionService;
use Illuminate\Support\Facades\DB;

/**
 * Erasure by anonymisation: identifying data is removed; anonymous geography
 * remains for aggregate counts. The phone hash is kept only on the suppression
 * list, so the person is never contacted again. Consent records (append-only
 * proof of decisions) keep only the hash.
 */
final class ErasureService
{
    public function __construct(private readonly SuppressionService $suppressions, private readonly AuditLogger $audit) {}

    public function anonymise(Supporter $supporter, ?User $by, string $reason, bool $suppress = true, RequestChannel $source = RequestChannel::Staff): void
    {
        if ($supporter->isErased()) {
            return;
        }

        if ($supporter->legal_hold) {
            throw new \RuntimeException('This record is under legal hold and cannot be erased until the hold is lifted.');
        }

        DB::transaction(function () use ($supporter, $by, $reason, $suppress, $source): void {
            if ($suppress && $supporter->phone_hash) {
                $this->suppressions->suppress($supporter->phone_hash, null, SuppressionReason::Erasure, $source, $by, 'Erasure: '.$supporter->reference);
            }

            $supporter->forceFill([
                'full_name' => '[erased]',
                'phone_encrypted' => null,
                'phone_hash' => null,
                'phone_last4' => null,
                'email_encrypted' => null,
                'polling_unit_id' => null,
                'wants_to_volunteer' => false,
                'rejection_reason' => null,
                'erased_at' => now(),
                'version' => $supporter->version + 1,
            ])->save();

            ContactAttempt::query()->where('supporter_id', $supporter->id)->update(['notes_encrypted' => null]);

            $this->audit->record('supporter.erased', $supporter, null, ['reason' => $reason], $by);
        });
    }
}
