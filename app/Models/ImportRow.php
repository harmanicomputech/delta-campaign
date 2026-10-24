<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $import_job_id
 * @property int $row_number
 * @property array<string, string|null> $payload
 * @property ImportRowStatus $status
 * @property ImportRowAction|null $action
 * @property list<string>|null $errors
 * @property list<string>|null $warnings
 */
class ImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'status' => ImportRowStatus::class,
            'action' => ImportRowAction::class,
        ];
    }

    /** @return BelongsTo<ImportJob, $this> */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
