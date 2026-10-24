<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Re-walks the audit chain and reports the first entry whose hash or link is wrong. */
final class AuditChainVerifier
{
    /**
     * @return array{valid: bool, checked: int, last_id: int|null, last_hash: string|null, broken_id: int|null, reason: string|null}
     */
    public function verify(?int $fromId = null, int $chunk = 2000): array
    {
        $previous = $fromId === null ? null : DB::table('audit_logs')->where('id', '<', $fromId)->orderByDesc('id')->value('hash');
        $checked = 0;
        $last = ['id' => null, 'hash' => null];
        $broken = null;

        DB::table('audit_logs')->when($fromId, fn ($q) => $q->where('id', '>=', $fromId))->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$previous, &$checked, &$last, &$broken): bool {
                foreach ($rows as $row) {
                    $checked++;

                    if ($row->previous_hash !== $previous) {
                        $broken = ['id' => $row->id, 'reason' => 'Link to the previous entry does not match (entry removed or reordered).'];

                        return false;
                    }

                    $entry = (array) $row;
                    $entry['occurred_at'] = Carbon::parse($row->occurred_at)->utc()->format('Y-m-d\TH:i:s.uP');
                    $entry['changes'] = $row->changes === null ? null : json_decode($row->changes, true);
                    $entry['context'] = $row->context === null ? null : json_decode($row->context, true);
                    $entry['actor_id'] = $row->actor_id === null ? null : (int) $row->actor_id;

                    if (! hash_equals($row->hash, AuditLogger::hashEntry($entry, $previous))) {
                        $broken = ['id' => $row->id, 'reason' => 'Entry content does not match its hash (entry altered).'];

                        return false;
                    }

                    $previous = $row->hash;
                    $last = ['id' => $row->id, 'hash' => $row->hash];
                }

                return true;
            });

        return [
            'valid' => $broken === null,
            'checked' => $checked,
            'last_id' => $last['id'],
            'last_hash' => $last['hash'],
            'broken_id' => $broken['id'] ?? null,
            'reason' => $broken['reason'] ?? null,
        ];
    }
}
