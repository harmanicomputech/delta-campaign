<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ImportType $type
 * @property ImportStatus $status
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $file_hash
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $warning_rows
 * @property int $created_count
 * @property int $updated_count
 * @property int $unchanged_count
 * @property string|null $error_report_path
 * @property string|null $failure_reason
 * @property bool $is_sample
 * @property int|null $created_by
 * @property int|null $confirmed_by
 * @property Carbon|null $validated_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
class ImportJob extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'is_sample' => 'boolean',
            'validated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** A previous completed import of byte-identical content, if any. */
    public function previousIdenticalImport(): ?self
    {
        return static::query()
            ->where('file_hash', $this->file_hash)
            ->where('status', ImportStatus::Completed)
            ->whereKeyNot($this->getKey())
            ->latest('completed_at')
            ->first();
    }
}
