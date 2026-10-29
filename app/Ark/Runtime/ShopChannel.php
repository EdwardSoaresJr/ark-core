<?php

declare(strict_types=1);

namespace App\Ark\Runtime;

/**
 * Names a shop channel. A single shop has no prefix.
 */
final class ShopChannel
{
    public static function prefix(): string
    {
        if (! app()->bound(ShopChannelScope::class)) {
            return '';
        }

        $id = app(ShopChannelScope::class)->id();
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('A shop is required before broadcasting.');
        }

        return 'shop.'.$id.'.';
    }

    public static function name(string $channel): string
    {
        return self::prefix().$channel;
    }
}
