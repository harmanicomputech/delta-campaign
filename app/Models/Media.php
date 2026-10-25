<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An uploaded public image with resized WebP variants.
 *
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property array<int|string, string>|null $variants
 * @property string|null $alt_en
 * @property string|null $alt_pcm
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['variants' => 'array', 'width' => 'integer', 'height' => 'integer'];
    }

    public function url(?int $width = null): string
    {
        $variants = $this->variants ?? [];
        $path = $this->path;

        if ($width !== null && $variants !== []) {
            ksort($variants);
            foreach ($variants as $w => $variantPath) {
                $path = $variantPath;
                if ((int) $w >= $width) {
                    break;
                }
            }
        }

        return Storage::disk($this->disk)->url($path);
    }

    public function srcset(): string
    {
        return collect($this->variants ?? [])->map(fn (string $path, int|string $w) => Storage::disk($this->disk)->url($path).' '.$w.'w')->implode(', ');
    }

    public function alt(?string $locale = null): string
    {
        return (string) (($locale ?? app()->getLocale()) === 'pcm' && filled($this->alt_pcm) ? $this->alt_pcm : $this->alt_en);
    }
}
