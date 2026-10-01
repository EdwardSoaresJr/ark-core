<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Visit-reason / concern tokens like @RO1677 stay plain text on authority.
 * Presentation turns same-customer shop numbers into staff links.
 */
final class RepairOrderMention
{
    public const PATTERN = '/(?<![\w.])@RO#?(\d+)\b/i';

    /**
     * @param  array<int|string, string>  $hrefByNumber  shop-facing RO number → staff show URL
     */
    public static function html(string $text, array $hrefByNumber = []): string
    {
        $escaped = e($text);

        if ($hrefByNumber === []) {
            return $escaped;
        }

        $normalized = [];

        foreach ($hrefByNumber as $number => $href) {
            $normalized[(int) $number] = $href;
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            function (array $match) use ($normalized): string {
                $number = (int) $match[1];
                $href = $normalized[$number] ?? null;

                if ($href === null) {
                    return $match[0];
                }

                return '<a class="ops-page-link" href="'.e($href).'">@RO'.$number.'</a>';
            },
            $escaped,
        );
    }

    /**
     * @return list<int>
     */
    public static function numbersIn(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    public static function token(int $shopNumber): string
    {
        return '@RO'.$shopNumber;
    }
}
