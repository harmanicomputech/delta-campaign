<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ConsentChannel $channel
 * @property ConsentPurpose $purpose
 * @property string $body_en
 * @property string|null $body_pcm
 * @property string|null $provider_template
 * @property string $status active | archived
 */
class MessageTemplate extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'active', 'version' => 1];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['channel' => ConsentChannel::class, 'purpose' => ConsentPurpose::class, 'version' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function body(string $language): string
    {
        return $language === 'pcm' && filled($this->body_pcm) ? (string) $this->body_pcm : $this->body_en;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
