<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Str;

/**
 * Stable customer Billing Class keys and explicit labor-column associations (#180).
 *
 * Display names remain customer-facing labels. Keys are additive JSON fields on
 * shop_settings.customer_types[] and never rewrite historical RO commercial stamps.
 *
 * Built-in labor links are explicit posture↔class pairs only — never fuzzy name matching.
 */
final class CustomerBillingClassIdentity
{
    /**
     * Built-in class key => labor matrix column value (ConcernBillingPosture / custom_*).
     * Business and Military intentionally omit — customer-only.
     *
     * @var array<string, string>
     */
    public const BUILTIN_LABOR_COLUMN_BY_CLASS_KEY = [
        'retail' => 'customer_pay',
        'fleet' => 'fleet',
        'warranty' => 'warranty_other',
        'repairpal' => 'repairpal',
        'wholesale' => 'wholesale',
        'internal' => 'internal',
        'comeback' => 'comeback',
    ];

    /**
     * @var array<string, string>
     */
    public const BUILTIN_CLASS_KEY_BY_DEFAULT_NAME = [
        'retail' => 'retail',
        'fleet' => 'fleet',
        'warranty' => 'warranty',
        'repairpal' => 'repairpal',
        'wholesale' => 'wholesale',
        'internal' => 'internal',
        'comeback' => 'comeback',
        'business' => 'business',
        'military' => 'military',
    ];

    /**
     * Resolve or mint a stable key for a customer_types row.
     * Prefer an existing key; otherwise map built-in names; otherwise slug the label.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $reservedKeys
     */
    public static function keyForRow(array $row, array $reservedKeys = []): string
    {
        $existing = trim((string) ($row['key'] ?? ''));
        if ($existing !== '' && preg_match('/^[a-z][a-z0-9_]{0,47}$/', $existing) === 1) {
            return $existing;
        }

        $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
        if ($name !== '' && isset(self::BUILTIN_CLASS_KEY_BY_DEFAULT_NAME[$name])) {
            $candidate = self::BUILTIN_CLASS_KEY_BY_DEFAULT_NAME[$name];
            if (! in_array($candidate, $reservedKeys, true) || $candidate === $existing) {
                return $candidate;
            }
        }

        $base = Str::slug($name !== '' ? $name : 'class', '_');
        $base = $base !== '' ? $base : 'class';
        $base = mb_substr(preg_replace('/[^a-z0-9_]/', '', Str::lower($base)) ?: 'class', 0, 40);
        if (preg_match('/^[a-z]/', $base) !== 1) {
            $base = 'c_'.$base;
        }

        $candidate = $base;
        $suffix = 2;
        while (in_array($candidate, $reservedKeys, true)) {
            $candidate = mb_substr($base, 0, 40).'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Explicit labor column for a customer class key, if any.
     */
    public static function laborColumnForClassKey(?string $classKey): ?string
    {
        $key = trim((string) $classKey);
        if ($key === '') {
            return null;
        }

        return self::BUILTIN_LABOR_COLUMN_BY_CLASS_KEY[$key] ?? null;
    }

    /**
     * Explicit customer class key for a labor matrix column value, if any.
     * Custom labor columns (custom_*) never invent a customer class by label.
     */
    public static function classKeyForLaborColumn(?string $laborColumn): ?string
    {
        $column = trim((string) $laborColumn);
        if ($column === '' || str_starts_with($column, 'custom_')) {
            return null;
        }

        foreach (self::BUILTIN_LABOR_COLUMN_BY_CLASS_KEY as $classKey => $linkedColumn) {
            if ($linkedColumn === $column) {
                return $classKey;
            }
        }

        // customer_pay is the Retail labor column.
        if ($column === ConcernBillingPosture::CustomerPay->value) {
            return 'retail';
        }

        return null;
    }

    /**
     * Ensure every row carries a unique key and, for built-ins, an explicit labor_column_key.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function ensureKeysOnRows(array $rows): array
    {
        $reserved = [];
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = self::keyForRow($row, $reserved);
            $reserved[] = $key;
            $row['key'] = $key;

            $explicitLabor = filled($row['labor_column_key'] ?? null)
                ? (string) $row['labor_column_key']
                : null;

            // Only keep labor_column_key when it is the built-in explicit link for this key,
            // or a previously stored explicit link that still matches the built-in map.
            $builtinLabor = self::laborColumnForClassKey($key);
            if ($explicitLabor !== null && $builtinLabor !== null && $explicitLabor === $builtinLabor) {
                $row['labor_column_key'] = $explicitLabor;
            } elseif ($builtinLabor !== null) {
                $row['labor_column_key'] = $builtinLabor;
            } else {
                unset($row['labor_column_key']);
            }

            $out[] = $row;
        }

        return $out;
    }

    public static function isCustomerOnlyClassKey(?string $classKey): bool
    {
        $key = trim((string) $classKey);

        return $key !== '' && self::laborColumnForClassKey($key) === null;
    }
}
