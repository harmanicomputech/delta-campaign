<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $type
 * @property ExportStatus $status
 * @property array<string, mixed>|null $filters
 * @property bool $include_contact
 * @property string $disk
 * @property string|null $path
 * @property int|null $row_count
 * @property int $requested_by
 * @property string|null $failure_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property int $download_count
 * @property Carbon $created_at
 */
class ExportJob extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'filters' => 'array',
            'include_contact' => 'boolean',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Completed && $this->expires_at?->isFuture() && $this->path !== null;
    }
}
