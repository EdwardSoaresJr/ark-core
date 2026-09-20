<?php

namespace App\Ark\Operations\Parts;

/**
 * Counts quoteable PartsTech cart lines. Cleared / qty-0 stubs must not hold the shop session.
 */
final class PartsTechCartItems
{
    /**
     * @param  array<string, mixed>  $cart
     */
    public static function liveCount(array $cart): int
    {
        $count = 0;

        foreach (data_get($cart, 'orders', []) as $order) {
            if (! is_array($order)) {
                continue;
            }

            foreach (data_get($order, 'items', []) as $item) {
                if (self::isLive($item)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public static function isLive(mixed $item): bool
    {
        if (! is_array($item) || ! filled($item['id'] ?? null)) {
            return false;
        }

        if (array_key_exists('quantity', $item) && (float) ($item['quantity'] ?? 0) <= 0) {
            return false;
        }

        $partNumber = trim((string) ($item['partNumber'] ?? data_get($item, 'builtItem.product.partNumberDisplay', '')));
        $partName = trim((string) ($item['partName'] ?? data_get($item, 'builtItem.product.title', '')));

        if ($partNumber !== '' || $partName !== '') {
            return true;
        }

        $hasIdentityKeys = array_key_exists('partNumber', $item)
            || array_key_exists('partName', $item)
            || array_key_exists('builtItem', $item);

        return ! $hasIdentityKeys;
    }
}
