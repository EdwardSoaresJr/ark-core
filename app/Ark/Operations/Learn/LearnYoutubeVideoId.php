<?php

namespace App\Ark\Operations\Learn;

final class LearnYoutubeVideoId
{
    public static function parse(?string $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input) === 1) {
            return $input;
        }

        if (preg_match('/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $input, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
