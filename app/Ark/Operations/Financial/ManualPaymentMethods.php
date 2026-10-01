<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Settings\ShopSettings;

final class ManualPaymentMethods
{
    /**
     * @var list<array{key: string, label: string}>
     */
    public const DEFAULTS = [
        ['key' => 'check', 'label' => 'Check'],
        ['key' => 'card', 'label' => 'External Card'],
        ['key' => 'eft', 'label' => 'EFT'],
    ];

    /**
     * @var list<string>
     */
    private const RESERVED = ['cash', 'terminal', 'keyed'];

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function current(): array
    {
        $stored = ShopSettings::current()->manual_payment_methods;

        if (! is_array($stored)) {
            return self::DEFAULTS;
        }

        return self::normalize($stored);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::current(), 'key');
    }

    public static function label(PaymentMethod|string|null $method): ?string
    {
        if ($method === null) {
            return null;
        }

        $key = PaymentMethod::stored($method);

        foreach (self::current() as $row) {
            if ($row['key'] === $key) {
                return $row['label'];
            }
        }

        return PaymentMethod::tryFrom($key)?->label() ?? $key;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array{key: string, label: string}>
     */
    public static function normalize(array $rows): array
    {
        $methods = [];
        $used = [];

        foreach ($rows as $row) {
            if (! is_array($row) || count($methods) >= 8) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $label = mb_substr($label, 0, 24);
            $key = self::key((string) ($row['key'] ?? ''), $label, $used);
            $used[] = $key;
            $methods[] = ['key' => $key, 'label' => $label];
        }

        return $methods;
    }

    /**
     * @param  list<string>  $used
     */
    private static function key(string $requested, string $label, array $used): string
    {
        $requested = strtolower(trim($requested));
        if (preg_match('/^[a-z0-9_]{1,32}$/', $requested) === 1 && ! in_array($requested, self::RESERVED, true) && ! in_array($requested, $used, true)) {
            return $requested;
        }

        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $label));
        $base = trim($base, '_');
        if ($base === '' || in_array($base, self::RESERVED, true)) {
            $base = 'method';
        }

        $base = mb_substr($base, 0, 32);
        $key = $base;
        $suffix = 2;

        while (in_array($key, $used, true) || in_array($key, self::RESERVED, true)) {
            $tail = '_'.$suffix;
            $key = mb_substr($base, 0, 32 - strlen($tail)).$tail;
            $suffix++;
        }

        return $key;
    }
}
