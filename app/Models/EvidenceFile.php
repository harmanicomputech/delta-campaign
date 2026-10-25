<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * An original evidence file on private storage. Never modified after upload;
 * sha256 is the hash of the bytes as received.
 */
class EvidenceFile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->uuid ??= (string) Str::uuid());
        static::updating(function (self $e): void {
            if ($e->isDirty(['disk', 'path', 'sha256', 'size', 'mime', 'original_name'])) {
                throw new \LogicException('Evidence files are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'integrity_ok' => 'boolean', 'size' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return MorphTo<Model, $this> */
    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
