<?php

namespace App\Ark\Operations\Labor;

final class ShopFixedCostLines
{
    /** @var list<array{key: string, label: string}> */
    public const LEGACY_COST_KEYS = [
        ['key' => 'rent', 'label' => 'Rent / mortgage'],
        ['key' => 'utilities', 'label' => 'Utilities'],
        ['key' => 'insurance', 'label' => 'Insurance'],
        ['key' => 'software', 'label' => 'Software & subscriptions'],
        ['key' => 'equipment', 'label' => 'Equipment & tools'],
        ['key' => 'office_payroll', 'label' => 'Office and advisor payroll'],
        ['key' => 'other', 'label' => 'Other fixed shop costs'],
    ];

    /**
     * @param  array<string, mixed>  $costs
     * @return list<array{label: string, amount: string, period: string}>
     */
    public static function fromLegacyCosts(array $costs): array
    {
        $lines = [];

        foreach (self::LEGACY_COST_KEYS as $item) {
            $amount = trim((string) ($costs[$item['key']] ?? ''));

            if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            $lines[] = [
                'label' => $item['label'],
                'amount' => $amount,
                'period' => ShopFixedCostPeriod::MONTHLY,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{label: string, amount: string, period: string}>
     */
    public static function normalize(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $amount = trim((string) ($line['amount'] ?? ''));

            if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'amount' => $amount,
                'period' => ShopFixedCostPeriod::normalize((string) ($line['period'] ?? ShopFixedCostPeriod::MONTHLY)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{label: string, amount: string, period: string}>  $lines
     */
    public static function monthlyTotal(array $lines): float
    {
        return round(collect($lines)->sum(
            fn (array $line): float => ShopFixedCostPeriod::toMonthly(
                (float) $line['amount'],
                $line['period'],
            ),
        ), 2);
    }
}
