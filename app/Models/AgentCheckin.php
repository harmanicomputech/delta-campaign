<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arrival or opening-checklist record from a polling agent. */
class AgentCheckin extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'occurred_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return BelongsTo<AgentAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AgentAssignment::class, 'agent_assignment_id');
    }
}
