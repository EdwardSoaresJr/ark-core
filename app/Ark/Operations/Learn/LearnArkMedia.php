<?php

namespace App\Ark\Operations\Learn;

final class LearnArkMedia
{
    public static function find(string $role, string $articleSlug, string $slot): ?LearnArticleMedia
    {
        return LearnArticleMedia::query()
            ->where('article_key', LearnArticleKey::make($role, $articleSlug))
            ->where('slot', $slot)
            ->first();
    }

    public static function videoSlot(string $videoKey): string
    {
        return 'video:'.$videoKey;
    }

    public static function image(string $role, string $articleSlug, string $filename): string
    {
        return self::resolveImageUrl($role, $articleSlug, $filename)
            ?? asset("images/learn/{$role}/{$articleSlug}/{$filename}");
    }

    public static function imageExists(string $role, string $articleSlug, string $filename): bool
    {
        return self::resolveImageUrl($role, $articleSlug, $filename) !== null;
    }

    public static function video(string $role, string $articleSlug, string $filename): string
    {
        return asset("videos/learn/{$role}/{$articleSlug}/{$filename}");
    }

    public static function videoExists(string $role, string $articleSlug, string $filename): bool
    {
        return is_file(public_path("videos/learn/{$role}/{$articleSlug}/{$filename}"));
    }

    public static function poster(string $role, string $articleSlug, string $filename): string
    {
        return self::image($role, $articleSlug, $filename);
    }

    /**
     * @return array{hasMedia: bool, kind: ?string, src: ?string, youtubeId: ?string, poster: ?string}
     */
    public static function resolveVideo(
        string $role,
        string $articleSlug,
        string $videoKey,
        string $legacyFilename,
        ?string $posterFile = null,
    ): array {
        $slot = self::videoSlot($videoKey);
        $uploaded = self::find($role, $articleSlug, $slot);

        if ($uploaded !== null) {
            $poster = self::resolvePosterUrl($role, $articleSlug, $posterFile);

            if ($uploaded->isYoutube()) {
                return [
                    'hasMedia' => true,
                    'kind' => 'youtube',
                    'src' => null,
                    'youtubeId' => $uploaded->youtube_video_id,
                    'poster' => $poster,
                ];
            }

            if ($uploaded->isVideo()) {
                return [
                    'hasMedia' => true,
                    'kind' => 'video',
                    'src' => self::mediaUrl($uploaded),
                    'youtubeId' => null,
                    'poster' => $poster,
                ];
            }
        }

        $hasLegacy = self::videoExists($role, $articleSlug, $legacyFilename);

        return [
            'hasMedia' => $hasLegacy,
            'kind' => $hasLegacy ? 'legacy' : null,
            'src' => $hasLegacy ? self::video($role, $articleSlug, $legacyFilename) : null,
            'youtubeId' => null,
            'poster' => self::resolvePosterUrl($role, $articleSlug, $posterFile),
        ];
    }

    private static function resolvePosterUrl(string $role, string $articleSlug, ?string $posterFile): ?string
    {
        if ($posterFile === null || $posterFile === '') {
            return null;
        }

        return self::resolveImageUrl($role, $articleSlug, $posterFile);
    }

    public static function mediaUrl(LearnArticleMedia $media): string
    {
        return route('operations.learn.media.show', $media);
    }

    private static function resolveImageUrl(string $role, string $articleSlug, string $filename): ?string
    {
        $uploaded = self::find($role, $articleSlug, $filename);

        if ($uploaded !== null && $uploaded->isImage()) {
            return self::mediaUrl($uploaded);
        }

        if (self::legacyImageExists($role, $articleSlug, $filename)) {
            return self::legacyImageUrl($role, $articleSlug, $filename);
        }

        return null;
    }

    private static function legacyImageExists(string $role, string $articleSlug, string $filename): bool
    {
        return is_file(public_path("images/learn/{$role}/{$articleSlug}/{$filename}"));
    }

    private static function legacyImageUrl(string $role, string $articleSlug, string $filename): string
    {
        return asset("images/learn/{$role}/{$articleSlug}/{$filename}");
    }
}
