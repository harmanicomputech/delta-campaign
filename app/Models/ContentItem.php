<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property ContentType $type
 * @property string $slug
 * @property ContentStatus $status
 * @property string $title_en
 * @property string $body_en
 * @property int $version
 */
class ContentItem extends Model
{
    use HasTranslations;

    /** Fields captured in each version snapshot. */
    public const array VERSIONED = ['title_en', 'title_pcm', 'summary_en', 'summary_pcm', 'body_en', 'body_pcm', 'slug', 'cover_media_id', 'lga_id'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'version' => 1, 'is_sample' => false];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'status' => ContentStatus::class,
            'publish_at' => 'datetime',
            'published_snapshot' => 'array',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'is_sample' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @param Builder<ContentItem> $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_snapshot')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->published_snapshot !== null && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** A field as the public sees it (from the published copy), in the visitor's language. */
    public function live(string $field, ?string $locale = null): ?string
    {
        $snapshot = $this->published_snapshot ?? [];
        $locale ??= app()->getLocale();
        $value = $locale === 'pcm' ? ($snapshot[$field.'_pcm'] ?? null) : null;

        return filled($value) ? $value : ($snapshot[$field.'_en'] ?? $snapshot[$field] ?? null);
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return BelongsTo<Lga, $this> */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ContentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class);
    }

    public function publicUrl(): ?string
    {
        return match ($this->type) {
            ContentType::News => route('public.news.show', $this->slug),
            ContentType::FactCheck => route('public.fact-checks.show', $this->slug),
            ContentType::Page => match ($this->slug) {
                'about' => route('public.about'),
                'manifesto' => route('public.manifesto'),
                default => null,
            },
        };
    }
}
