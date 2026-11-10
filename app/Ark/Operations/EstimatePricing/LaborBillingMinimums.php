<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Shop-level (and RO-stamped) billing-class minimum configuration.
 *
 * @phpstan-type MinimumRow array{
 *     mode: string,
 *     hours: string,
 *     shortfall_rate_cents: int|null,
 *     shortfall_rate_source: string
 * }
 */
final class LaborBillingMinimums
{
    public const SHORTFALL_STANDARD_MECHANICAL = 'standard_mechanical';

    public const SHORTFALL_CUSTOM = 'custom';

    /**
     * @param  array<string, MinimumRow>  $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {}

    public static function disabled(): self
    {
        return new self([]);
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromStored(?array $raw): self
    {
        if ($raw === null || $raw === []) {
            return self::disabled();
        }

        $rows = [];

        foreach (LaborWorkTypeMatrix::minimumConfigurablePostures() as $posture) {
            $entry = $raw[$posture->value] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $mode = LaborBillingMinimumMode::tryFrom((string) ($entry['mode'] ?? 'none'))
                ?? LaborBillingMinimumMode::None;
            $hours = number_format((float) ($entry['hours'] ?? 0), 2, '.', '');
            $source = (string) ($entry['shortfall_rate_source'] ?? self::SHORTFALL_STANDARD_MECHANICAL);

            if (! in_array($source, [self::SHORTFALL_STANDARD_MECHANICAL, self::SHORTFALL_CUSTOM], true)) {
                $source = self::SHORTFALL_STANDARD_MECHANICAL;
            }

            $rows[$posture->value] = [
                'mode' => $mode->value,
                'hours' => $hours,
                'shortfall_rate_cents' => isset($entry['shortfall_rate_cents'])
                    ? (int) $entry['shortfall_rate_cents']
                    : null,
                'shortfall_rate_source' => $source,
            ];
        }

        return new self($rows);
    }

    /**
     * @param  array<string, array<string, mixed>>  $input
     * @return array<string, MinimumRow>
     */
    public static function normalizeInput(array $input, ShopSettings $settings): array
    {
        $standardMechanical = app(LaborPolicyResolver::class)
            ->resolve(ConcernBillingPosture::CustomerPay, 'general_repair')
            ->hourlyRateCents;

        $rows = [];

        foreach (LaborWorkTypeMatrix::minimumConfigurablePostures() as $posture) {
            $entry = $input[$posture->value] ?? [];
            $mode = LaborBillingMinimumMode::tryFrom((string) ($entry['mode'] ?? 'none'))
                ?? LaborBillingMinimumMode::None;
            $hours = number_format(max(0, (float) ($entry['hours'] ?? 0)), 2, '.', '');
            $source = (string) ($entry['shortfall_rate_source'] ?? self::SHORTFALL_STANDARD_MECHANICAL);

            if (! in_array($source, [self::SHORTFALL_STANDARD_MECHANICAL, self::SHORTFALL_CUSTOM], true)) {
                $source = self::SHORTFALL_STANDARD_MECHANICAL;
            }

            $customCents = filled($entry['shortfall_rate'] ?? null)
                ? (int) round(((float) $entry['shortfall_rate']) * 100)
                : (isset($entry['shortfall_rate_cents']) ? (int) $entry['shortfall_rate_cents'] : null);

            $rows[$posture->value] = [
                'mode' => $mode->value,
                'hours' => $mode === LaborBillingMinimumMode::None ? '0.00' : $hours,
                'shortfall_rate_cents' => $source === self::SHORTFALL_CUSTOM
                    ? ($customCents ?? $standardMechanical)
                    : $standardMechanical,
                'shortfall_rate_source' => $source,
            ];
        }

        return $rows;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function enablesAnyMinimum(): bool
    {
        foreach ($this->rows as $row) {
            if (($row['mode'] ?? 'none') !== LaborBillingMinimumMode::None->value
                && (float) ($row['hours'] ?? 0) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return MinimumRow|null
     */
    public function forPosture(ConcernBillingPosture $posture): ?array
    {
        $normalized = match ($posture) {
            ConcernBillingPosture::Default => ConcernBillingPosture::CustomerPay,
            ConcernBillingPosture::Warranty => ConcernBillingPosture::WarrantyOther,
            ConcernBillingPosture::WarrantyRepairPal => ConcernBillingPosture::RepairPal,
            default => $posture,
        };

        return $this->rows[$normalized->value] ?? null;
    }

    /**
     * @return array<string, MinimumRow>
     */
    public function toArray(): array
    {
        return $this->rows;
    }

    public function shortfallRateCents(ConcernBillingPosture $posture, ShopSettings $settings): int
    {
        $row = $this->forPosture($posture);

        if ($row === null) {
            return $settings->defaultLaborRateCents();
        }

        // Prefer the stamped/saved cents so RO profiles stay immutable after create.
        if (isset($row['shortfall_rate_cents']) && $row['shortfall_rate_cents'] !== null) {
            return (int) $row['shortfall_rate_cents'];
        }

        if (($row['shortfall_rate_source'] ?? self::SHORTFALL_STANDARD_MECHANICAL) === self::SHORTFALL_CUSTOM) {
            return $settings->defaultLaborRateCents();
        }

        try {
            return app(LaborPolicyResolver::class)
                ->resolve(ConcernBillingPosture::CustomerPay, 'general_repair')
                ->hourlyRateCents;
        } catch (\Throwable) {
            return $settings->defaultLaborRateCents();
        }
    }
}
