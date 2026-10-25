<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public images. Every upload is decoded and re-encoded as WebP at a few widths:
 * this strips EXIF metadata (including GPS location), normalises orientation of
 * the pixels actually served, and keeps pages light on slow connections.
 */
final class MediaService
{
    public const array WIDTHS = [480, 960, 1600];

    public const array MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function storeImage(UploadedFile $file, User $by, ?string $altEn = null, ?string $altPcm = null): Media
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['image' => __('content.image_type')]);
        }

        $source = match ($mime) {
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => @imagecreatefromjpeg($file->getRealPath()),
        };
        if ($source === false) {
            throw ValidationException::withMessages(['image' => __('content.image_unreadable')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $disk = (string) config('campaign.media.disk');
        $base = 'media/'.now()->format('Y/m').'/'.Str::uuid();
        $variants = [];

        foreach (self::WIDTHS as $target) {
            if ($target > $width && $variants !== []) {
                break;
            }
            $w = min($target, $width);
            $h = (int) round($height * $w / $width);
            $canvas = imagecreatetruecolor($w, $h);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);

            ob_start();
            imagewebp($canvas, null, 80);
            $bytes = (string) ob_get_clean();
            imagedestroy($canvas);

            $path = "{$base}-{$w}.webp";
            Storage::disk($disk)->put($path, $bytes);
            $variants[$w] = $path;
        }
        imagedestroy($source);

        return Media::query()->create([
            'disk' => $disk,
            'path' => end($variants),
            'variants' => $variants,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime' => 'image/webp',
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            'alt_en' => $altEn !== null ? mb_substr($altEn, 0, 250) : null,
            'alt_pcm' => $altPcm !== null ? mb_substr($altPcm, 0, 250) : null,
            'uploaded_by' => $by->id,
        ]);
    }
}
