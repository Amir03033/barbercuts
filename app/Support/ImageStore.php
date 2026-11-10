<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Slaat uploads op als WebP, verkleind tot een maximum (performance-budget §10).
 */
class ImageStore
{
    public static function disk(): string
    {
        $default = (string) config('filesystems.default');

        return $default === 'local' ? 'public' : $default;
    }

    public static function storeWebp(UploadedFile $file, string $directory, int $maxWidth, int $maxHeight): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Dit bestand is geen geldige afbeelding.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($target, null, 82);
        $contents = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(32).'.webp';
        Storage::disk(self::disk())->put($path, $contents, 'public');

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::disk())->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::disk())->url($path) : null;
    }
}
