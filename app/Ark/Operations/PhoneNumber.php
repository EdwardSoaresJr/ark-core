<?php

namespace App\Ark\Operations;

use Illuminate\Support\Str;

final class PhoneNumber
{
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', trim((string) $phone)) ?? '';
    }

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = self::digits($phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return $digits;
        }

        return Str::limit($digits, 32, '');
    }

    public static function display(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = self::digits($phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4),
            );
        }

        return trim($phone);
    }

    public static function toE164(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        if (strlen($normalized) === 10) {
            return '+1'.$normalized;
        }

        $digits = self::digits($phone);

        return $digits !== '' ? '+'.$digits : null;
    }

    public static function telUri(?string $phone): ?string
    {
        $digits = self::digits($phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return 'tel:+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return 'tel:+'.$digits;
        }

        return 'tel:'.$digits;
    }
}
