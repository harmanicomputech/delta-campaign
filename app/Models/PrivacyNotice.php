<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A version of the privacy notice. Consent records reference the version shown.
 *
 * @property int $id
 * @property string $version
 * @property string $title
 * @property string $summary_en
 * @property string|null $summary_pcm
 * @property string $body_en
 * @property string|null $body_pcm
 * @property Carbon|null $published_at
 */
class PrivacyNotice extends Model
{
    protected $fillable = ['version', 'title', 'summary_en', 'summary_pcm', 'body_en', 'body_pcm'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** The notice in force: the latest published version. */
    public static function current(): ?self
    {
        return static::query()->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->first();
    }

    public function summary(?string $locale = null): string
    {
        return (($locale ?? app()->getLocale()) === 'pcm' && $this->summary_pcm) ? $this->summary_pcm : $this->summary_en;
    }

    public function body(?string $locale = null): string
    {
        return (($locale ?? app()->getLocale()) === 'pcm' && $this->body_pcm) ? $this->body_pcm : $this->body_en;
    }
}
