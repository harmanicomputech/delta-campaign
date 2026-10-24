<?php

declare(strict_types=1);

namespace App\Services\Supporters;

use App\Models\Supporter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Possible duplicates that the phone hash cannot catch (a person registered
 * twice with different numbers): similar names (pg_trgm) in the same ward, or
 * the same LGA when no ward is known. Shown for human review, never merged
 * automatically.
 */
final class DuplicateFinder
{
    /** @return Collection<int, Supporter> */
    public function candidates(Supporter $supporter, User $viewer, int $limit = 5): Collection
    {
        if ($supporter->isErased()) {
            return collect();
        }

        $threshold = (float) config('campaign.privacy.duplicate_similarity');

        return Supporter::query()
            ->visibleTo($viewer, 'supporters.view')
            ->whereKeyNot($supporter->id)
            ->whereNull('erased_at')
            ->when($supporter->ward_id, fn ($q) => $q->where('ward_id', $supporter->ward_id), fn ($q) => $q->where('lga_id', $supporter->lga_id))
            ->whereRaw('similarity(full_name, ?) >= ?', [$supporter->full_name, $threshold])
            ->orderByRaw('similarity(full_name, ?) desc', [$supporter->full_name])
            ->select('supporters.*', DB::raw('round(similarity(full_name, '.DB::getPdo()->quote($supporter->full_name).')::numeric, 2) as name_similarity'))
            ->limit($limit)
            ->get();
    }
}
