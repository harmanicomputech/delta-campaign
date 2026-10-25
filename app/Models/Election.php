<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ElectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An election being observed. Parties and the opening checklist are entered
 * by administrators from official sources; nothing is pre-filled.
 *
 * @property int $id
 * @property ElectionStatus $status
 * @property list<array{key: string, label: string}> $checklist
 */
class Election extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'setup', 'is_sample' => false];

    protected function casts(): array
    {
        return ['status' => ElectionStatus::class, 'election_date' => 'date', 'checklist' => 'array', 'turnout_times' => 'array', 'is_sample' => 'boolean'];
    }

    /** The election agents currently report for (at most one is active). */
    public static function active(): ?self
    {
        return self::query()->where('status', ElectionStatus::Active)->latest('election_date')->first();
    }

    /** The active election, or the most recent one, for staff screens. */
    public static function current(): ?self
    {
        return self::active() ?? self::query()->latest('election_date')->first();
    }

    /** @return HasMany<ElectionParty, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(ElectionParty::class)->orderBy('position')->orderBy('code');
    }

    /** @return list<string> */
    public function partyCodes(): array
    {
        return $this->parties->pluck('code')->all();
    }
}
