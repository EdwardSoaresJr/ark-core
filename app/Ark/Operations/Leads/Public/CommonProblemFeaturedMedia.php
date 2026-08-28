<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Optional featured media gallery on common-problem / local-intent pages.
 * Shop overrides (Website → Page media) win over config/JSON stubs.
 *
 * Storage shape per slug: list of { id, path, alt, caption, …contribution trace } — first item is primary.
 */
final class CommonProblemFeaturedMedia
{
    public const STORAGE_PREFIX = 'common-problem-media/';

    /**
     * @return array<string, list<array{id: string, path: string, alt: string, caption: string}>>
     */
    public static function allStored(): array
    {
        $raw = ShopPublicSurfaceRaw::read();
        $stored = $raw['common_problem_featured_media'] ?? [];

        if (! is_array($stored)) {
            return [];
        }

        $normalized = [];

        foreach ($stored as $slug => $media) {
            if (! is_string($slug)) {
                continue;
            }

            $gallery = self::normalizeGallery($media);

            if ($gallery !== []) {
                $normalized[$slug] = $gallery;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $fromProblem
     * @return list<array{id: string, path: string, alt: string, caption: string}>
     */
    public static function rawForSlug(string $slug, ?array $fromProblem): array
    {
        $stored = self::allStored()[$slug] ?? null;

        if ($stored !== null) {
            return $stored;
        }

        if (! is_array($fromProblem)) {
            return [];
        }

        return self::normalizeGallery($fromProblem);
    }

    /**
     * Primary (first) image for SEO — Open Graph / Twitter.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $raw
     * @return array{url: string, alt: string, caption: string|null}|null
     */
    public static function primaryForDisplay(?array $raw): ?array
    {
        $gallery = self::galleryForDisplay($raw);

        return $gallery[0] ?? null;
    }

    /**
     * One image for the page hero — random on each request when multiple exist.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $raw
     * @return array{url: string, alt: string, caption: string|null, gallery_index: int}|null
     */
    public static function featuredForDisplay(?array $raw): ?array
    {
        $gallery = self::galleryForDisplay($raw);

        if ($gallery === []) {
            return null;
        }

        $index = count($gallery) === 1 ? 0 : random_int(0, count($gallery) - 1);
        $item = $gallery[$index];

        return [
            'url' => $item['url'],
            'url_large' => $item['url_large'],
            'width' => $item['width'],
            'height' => $item['height'],
            'alt' => $item['alt'],
            'caption' => $item['caption'],
            'gallery_index' => $index,
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $raw
     * @return list<array{url: string, alt: string, caption: string|null}>
     */
    public static function galleryForDisplay(?array $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach (self::normalizeGallery($raw) as $item) {
            $display = self::displayItem($item);

            if ($display !== null) {
                $items[] = $display;
            }
        }

        return $items;
    }

    /**
     * @return list<array{id: string, path: string, alt: string, caption: string}>
     */
    public static function forSlug(string $slug): array
    {
        return self::allStored()[$slug] ?? [];
    }

    public static function storeUpload(string $slug, UploadedFile $file): string
    {
        $optimizedPath = CommonProblemFeaturedMediaOptimizer::storeFromUpload($slug, $file);

        if ($optimizedPath !== null) {
            return $optimizedPath;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs(self::STORAGE_PREFIX.$slug, $filename, 'public');
    }

    /**
     * @param  list<array{id?: string, path: string, alt: string, caption?: string}>  $items
     */
    public static function persistGalleryForSlug(string $slug, array $items): void
    {
        if (! in_array($slug, CommonProblemRegistry::slugs(), true)) {
            abort(404);
        }

        $raw = ShopPublicSurfaceRaw::read();
        $map = is_array($raw['common_problem_featured_media'] ?? null)
            ? $raw['common_problem_featured_media']
            : [];

        $existing = self::forSlug($slug);
        $existingPaths = collect($existing)->pluck('path')->all();

        $normalized = [];

        foreach ($items as $item) {
            $entry = self::normalizeItem($item);

            if ($entry !== null) {
                $normalized[] = $entry;
            }
        }

        $keptPaths = collect($normalized)->pluck('path')->all();

        foreach ($existingPaths as $path) {
            if (! in_array($path, $keptPaths, true)) {
                self::deleteStoredPath($path);
            }
        }

        if ($normalized === []) {
            unset($map[$slug]);
        } else {
            $map[$slug] = $normalized;
        }

        $raw['common_problem_featured_media'] = $map;

        ShopPublicSurfaceRaw::write($raw);
    }

    public static function deleteStoredPath(string $path): void
    {
        if (! str_starts_with($path, self::STORAGE_PREFIX)) {
            return;
        }

        CommonProblemFeaturedMediaOptimizer::deleteVariants($path);
    }

    public static function isLiveForSlug(string $slug): bool
    {
        return self::galleryForDisplay(self::rawForSlug($slug, null)) !== [];
    }

    /**
     * Returns a validation error message, or null when ALT text is descriptive enough.
     */
    public static function altTextError(string $alt): ?string
    {
        $alt = trim($alt);

        if ($alt === '') {
            return 'ALT text is required when a featured image is present.';
        }

        if (mb_strlen($alt) < 25) {
            return 'Write a descriptive ALT sentence (at least 25 characters) — technician, test or repair, and vehicle if known.';
        }

        $lower = mb_strtolower($alt);

        $exactGeneric = [
            'photo',
            'image',
            'picture',
            'img',
            'engine',
            'car',
            'mechanic',
            'repair',
            'auto',
            'vehicle',
        ];

        if (in_array($lower, $exactGeneric, true)) {
            return 'Avoid single-word ALT text like "engine" or "photo". Describe what the photo shows.';
        }

        if (preg_match('/^(photo|image|picture|img)(\s+of)?\s*[\w\s]{0,12}$/u', $lower) === 1) {
            return 'Describe the repair step or test — not just "photo of engine".';
        }

        $wordCount = count(preg_split('/\s+/u', $alt, -1, PREG_SPLIT_NO_EMPTY));

        if ($wordCount < 3) {
            return 'Use a full phrase — technician, test or repair step, and vehicle when possible.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $raw
     * @return list<array{id: string, path: string, alt: string, caption: string}>
     */
    public static function normalizeGallery(array $raw): array
    {
        if (isset($raw['path'])) {
            $item = self::normalizeItem($raw);

            return $item !== null ? [$item] : [];
        }

        if (! array_is_list($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $item = self::normalizeItem($entry);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $media
     * @return array{
     *     id: string,
     *     path: string,
     *     alt: string,
     *     caption: string,
     *     source_repair_order_id?: int,
     *     source_media_id?: int,
     *     contributed_by?: int,
     *     contributed_at?: string
     * }|null
     */
    private static function normalizeItem(array $media): ?array
    {
        $path = trim((string) ($media['path'] ?? ''));

        if ($path === '') {
            return null;
        }

        $id = trim((string) ($media['id'] ?? ''));

        if ($id === '') {
            $id = substr(hash('sha256', $path), 0, 12);
        }

        $item = [
            'id' => $id,
            'path' => $path,
            'alt' => trim((string) ($media['alt'] ?? '')),
            'caption' => trim((string) ($media['caption'] ?? '')),
        ];

        foreach (['source_repair_order_id', 'source_media_id', 'contributed_by'] as $key) {
            if (isset($media[$key]) && is_numeric($media[$key])) {
                $item[$key] = (int) $media[$key];
            }
        }

        $contributedAt = trim((string) ($media['contributed_at'] ?? ''));

        if ($contributedAt !== '') {
            $item['contributed_at'] = $contributedAt;
        }

        return $item;
    }

    /**
     * @param  array{id: string, path: string, alt: string, caption: string}  $item
     * @return array{url: string, alt: string, caption: string|null}|null
     */
    private static function displayItem(array $item): ?array
    {
        if (trim($item['alt']) === '') {
            return null;
        }

        $displayPath = $item['path'];
        $displayUrl = PublicSurfaceSettings::photoUrl($displayPath);

        if ($displayUrl === '') {
            return null;
        }

        $largePath = CommonProblemFeaturedMediaOptimizer::largePathForDisplayPath($displayPath);
        $largeUrl = CommonProblemFeaturedMediaOptimizer::isDisplayPath($displayPath)
            && Storage::disk('public')->exists($largePath)
            ? PublicSurfaceSettings::photoUrl($largePath)
            : $displayUrl;
        $dimensions = CommonProblemFeaturedMediaOptimizer::isDisplayPath($displayPath)
            ? CommonProblemFeaturedMediaOptimizer::dimensionsForDisplayPath($displayPath)
            : null;

        return [
            'url' => $displayUrl,
            'url_large' => $largeUrl,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'alt' => $item['alt'],
            'caption' => self::nonEmptyString($item['caption'] ?? null),
        ];
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
