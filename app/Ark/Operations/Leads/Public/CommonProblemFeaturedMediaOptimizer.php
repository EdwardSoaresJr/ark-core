<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CommonProblemFeaturedMediaOptimizer
{
    public const DISPLAY_MAX_WIDTH = 960;

    public const LARGE_MAX_WIDTH = 1440;

    public const WEBP_QUALITY = 82;

    public const JPEG_QUALITY = 82;

    public static function isAvailable(): bool
    {
        return extension_loaded('gd') && (self::supportsWebp() || function_exists('imagejpeg'));
    }

    public static function supportsWebp(): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }

        $info = gd_info();

        return (bool) ($info['WebP Support'] ?? false);
    }

    public static function outputExtension(): string
    {
        return self::supportsWebp() ? 'webp' : 'jpg';
    }

    public static function storeFromUpload(string $slug, UploadedFile $file): ?string
    {
        if (! self::isAvailable()) {
            return null;
        }

        $tempPath = $file->getRealPath();

        if (! is_string($tempPath) || $tempPath === '' || ! is_readable($tempPath)) {
            return null;
        }

        $image = self::loadImage($tempPath, (string) $file->getMimeType());

        if ($image === null) {
            return null;
        }

        $uuid = Str::uuid()->toString();
        $prefix = CommonProblemFeaturedMedia::STORAGE_PREFIX.$slug.'/';
        $displayPath = $prefix.$uuid.'-display.'.self::outputExtension();
        $largePath = $prefix.$uuid.'-large.'.self::outputExtension();

        $disk = Storage::disk('public');
        $disk->makeDirectory($prefix);

        try {
            self::writeVariant($image, $disk->path($displayPath), self::DISPLAY_MAX_WIDTH);
            self::writeVariant($image, $disk->path($largePath), self::LARGE_MAX_WIDTH);
        } finally {
            imagedestroy($image);
        }

        return $displayPath;
    }

    /**
     * @return array{display: string, large: string, width: int, height: int}|null
     */
    public static function optimizeStoredPath(string $path): ?array
    {
        if (! self::isAvailable() || ! self::isOptimizableSourcePath($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $image = self::loadImage($disk->path($path), self::mimeForPath($path));

        if ($image === null) {
            return null;
        }

        $directory = trim(dirname($path), '.');
        $uuid = Str::uuid()->toString();
        $extension = self::outputExtension();
        $displayPath = $directory.'/'.$uuid.'-display.'.$extension;
        $largePath = $directory.'/'.$uuid.'-large.'.$extension;

        try {
            $displayDimensions = self::writeVariant($image, $disk->path($displayPath), self::DISPLAY_MAX_WIDTH);
            self::writeVariant($image, $disk->path($largePath), self::LARGE_MAX_WIDTH);
        } finally {
            imagedestroy($image);
        }

        $disk->delete($path);

        return [
            'display' => $displayPath,
            'large' => $largePath,
            'width' => $displayDimensions['width'],
            'height' => $displayDimensions['height'],
        ];
    }

    public static function deleteVariants(string $path): void
    {
        $disk = Storage::disk('public');
        $paths = [$path];

        if (self::isDisplayPath($path)) {
            $paths[] = self::largePathForDisplayPath($path);
        } elseif (self::isOptimizableSourcePath($path)) {
            foreach (['webp', 'jpg', 'jpeg'] as $extension) {
                $paths[] = preg_replace('/\.(jpe?g|png|webp)$/i', '-display.'.$extension, $path) ?? $path;
                $paths[] = preg_replace('/\.(jpe?g|png|webp)$/i', '-large.'.$extension, $path) ?? $path;
            }
        }

        foreach (array_unique($paths) as $candidate) {
            if ($candidate !== '' && $disk->exists($candidate)) {
                $disk->delete($candidate);
            }
        }
    }

    public static function isDisplayPath(string $path): bool
    {
        return preg_match('/-display\.(webp|jpe?g)$/i', $path) === 1;
    }

    public static function largePathForDisplayPath(string $displayPath): string
    {
        if (preg_match('/-display\.(webp|jpe?g)$/i', $displayPath, $matches) !== 1) {
            return $displayPath;
        }

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);

        return preg_replace('/-display\.(webp|jpe?g)$/i', '-large.'.$extension, $displayPath) ?? $displayPath;
    }

    public static function dimensionsForDisplayPath(string $displayPath): ?array
    {
        if (! extension_loaded('gd') || ! Storage::disk('public')->exists($displayPath)) {
            return null;
        }

        $size = @getimagesize(Storage::disk('public')->path($displayPath));

        if (! is_array($size)) {
            return null;
        }

        return [
            'width' => (int) ($size[0] ?? 0),
            'height' => (int) ($size[1] ?? 0),
        ];
    }

    private static function isOptimizableSourcePath(string $path): bool
    {
        if (! str_starts_with($path, CommonProblemFeaturedMedia::STORAGE_PREFIX)) {
            return false;
        }

        if (self::isDisplayPath($path) || preg_match('/-large\.(webp|jpe?g)$/i', $path) === 1) {
            return false;
        }

        return preg_match('/\.(jpe?g|png|webp)$/i', $path) === 1;
    }

    private static function mimeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private static function loadImage(string $path, string $mime): ?\GdImage
    {
        $image = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => self::supportsWebp() ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * @return array{width: int, height: int}
     */
    private static function writeVariant(\GdImage $source, string $destPath, int $maxWidth): array
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('Invalid image dimensions.');
        }

        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round($height * ($targetWidth / $width));
        $working = $source;

        if ($targetWidth !== $width) {
            $resized = imagescale($source, $targetWidth, $targetHeight);

            if (! $resized instanceof \GdImage) {
                throw new RuntimeException('Unable to resize image.');
            }

            $working = $resized;
        }

        $saved = self::supportsWebp() && str_ends_with(strtolower($destPath), '.webp')
            ? imagewebp($working, $destPath, self::WEBP_QUALITY)
            : imagejpeg($working, $destPath, self::JPEG_QUALITY);

        if ($working !== $source) {
            imagedestroy($working);
        }

        if (! $saved) {
            throw new RuntimeException('Unable to write optimized image.');
        }

        return [
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}
