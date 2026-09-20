<?php

namespace App\Ark\Operations\Labor;

final class ShopOverheadSnapshot
{
    /** @var list<string> */
    private const FIXED_COST_KEYS = ['rent', 'utilities', 'insurance', 'software', 'equipment', 'office_payroll', 'other'];

    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(private readonly array $state) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromState(array $state): self
    {
        return new self($state);
    }

    public function monthlyFixedOverheadTotal(): float
    {
        return ShopFixedCostLines::monthlyTotal($this->fixedCostLines());
    }

    /**
     * @return list<array{label: string, amount: string, period: string}>
     */
    public function fixedCostLines(): array
    {
        if (is_array($this->state['fixed_cost_lines'] ?? null) && $this->state['fixed_cost_lines'] !== []) {
            return ShopFixedCostLines::normalize($this->state['fixed_cost_lines']);
        }

        $costs = is_array($this->state['costs'] ?? null) ? $this->state['costs'] : [];

        return ShopFixedCostLines::fromLegacyCosts($costs);
    }

    public function monthlyProcessingCost(): float
    {
        $volume = $this->parseAmount($this->state['monthly_card_volume'] ?? null);
        $rate = $this->parseNonNegativePercent(
            $this->state['card_processing_percent'] ?? null,
            ShopPaymentProcessingOverhead::DEFAULT_CARD_PROCESSING_PERCENT,
        );

        return ShopPaymentProcessingOverhead::monthlyProcessingCost($volume, $rate);
    }

    public function monthlyFinancingCost(): float
    {
        $fixed = $this->parseAmount($this->state['fixed_monthly_financing_payment'] ?? null);

        if ($fixed > 0) {
            return round($fixed, 2);
        }

        $volume = $this->parseAmount($this->state['monthly_card_volume'] ?? null);
        $processingRate = $this->parseNonNegativePercent(
            $this->state['card_processing_percent'] ?? null,
            ShopPaymentProcessingOverhead::DEFAULT_CARD_PROCESSING_PERCENT,
        );
        $holdbackRate = $this->parseNonNegativePercent($this->state['merchant_financing_holdback_percent'] ?? null);

        return ShopPaymentProcessingOverhead::monthlyFinancingCost(
            $volume,
            $processingRate,
            $holdbackRate,
        );
    }

    public function monthlyOverheadTotal(): float
    {
        return round(
            $this->monthlyFixedOverheadTotal()
            + $this->monthlyProcessingCost()
            + $this->monthlyFinancingCost(),
            2,
        );
    }

    /**
     * Advisor, front desk, and other non-billing staff payroll from the fixed-cost worksheet.
     */
    public function monthlyOfficePayrollTotal(): float
    {
        $fromLines = round(collect($this->fixedCostLines())->sum(function (array $line): float {
            $label = mb_strtolower(trim($line['label']));

            if (! in_array($label, ['office and advisor payroll', 'payroll'], true)) {
                return 0.0;
            }

            return ShopFixedCostPeriod::toMonthly((float) $line['amount'], $line['period']);
        }), 2);

        if ($fromLines > 0) {
            return min($fromLines, $this->monthlyFixedOverheadTotal());
        }

        $costs = is_array($this->state['costs'] ?? null) ? $this->state['costs'] : [];
        $legacy = (float) ($costs['office_payroll'] ?? 0);

        return $legacy > 0 ? round($legacy, 2) : 0.0;
    }

    public function monthlyBillableHours(): ?float
    {
        $technicians = $this->parseInt($this->state['technician_count'] ?? null);
        if ($technicians === null || $technicians <= 0) {
            return null;
        }

        return ShopOverheadCalculator::monthlyBillableHours(
            $technicians,
            $this->parseFloat($this->state['workday_hours'] ?? null, ShopOverheadCalculator::DEFAULT_WORKDAY_HOURS),
            $this->parseNonNegativePercent(
                $this->state['billable_utilization'] ?? null,
                ShopOverheadCalculator::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
            ),
            $this->parseFloat($this->state['workdays_per_month'] ?? null, ShopOverheadCalculator::DEFAULT_WORKDAYS_PER_MONTH),
        );
    }

    public function overheadPerBilledHour(): ?float
    {
        return ShopOverheadCalculator::calculate(
            $this->monthlyOverheadTotal(),
            $this->parseInt($this->state['technician_count'] ?? null) ?? 0,
            $this->parseFloat($this->state['workday_hours'] ?? null, ShopOverheadCalculator::DEFAULT_WORKDAY_HOURS),
            $this->parseNonNegativePercent(
                $this->state['billable_utilization'] ?? null,
                ShopOverheadCalculator::DEFAULT_BILLABLE_UTILIZATION_PERCENT,
            ),
            $this->parseFloat($this->state['workdays_per_month'] ?? null, ShopOverheadCalculator::DEFAULT_WORKDAYS_PER_MONTH),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedState(): array
    {
        $emptyCosts = collect(self::FIXED_COST_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => ''])
            ->all();

        return [
            'fixed_cost_lines' => $this->fixedCostLines(),
            'costs' => $emptyCosts,
            'monthly_card_volume' => (string) ($this->state['monthly_card_volume'] ?? ''),
            'card_processing_percent' => (string) ($this->state['card_processing_percent'] ?? ShopPaymentProcessingOverhead::DEFAULT_CARD_PROCESSING_PERCENT),
            'merchant_financing_holdback_percent' => (string) ($this->state['merchant_financing_holdback_percent'] ?? ''),
            'fixed_monthly_financing_payment' => (string) ($this->state['fixed_monthly_financing_payment'] ?? ''),
            'technician_count' => (string) ($this->state['technician_count'] ?? '1'),
            'workdays_per_month' => (string) ($this->state['workdays_per_month'] ?? ShopOverheadCalculator::DEFAULT_WORKDAYS_PER_MONTH),
            'workday_hours' => (string) ($this->state['workday_hours'] ?? ShopOverheadCalculator::DEFAULT_WORKDAY_HOURS),
            'billable_utilization' => (string) ($this->state['billable_utilization'] ?? ShopOverheadCalculator::DEFAULT_BILLABLE_UTILIZATION_PERCENT),
            'overhead_tab' => (string) ($this->state['overhead_tab'] ?? 'fixed-costs'),
        ];
    }

    private function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $parsed = is_numeric($value) ? (float) $value : 0.0;

        return $parsed > 0 ? $parsed : 0.0;
    }

    private function parseNonNegativePercent(mixed $value, float $fallback = 0.0): float
    {
        if ($value === null || $value === '') {
            return max($fallback, 0.0);
        }

        $parsed = is_numeric($value) ? (float) $value : $fallback;

        return max($parsed, 0.0);
    }

    private function parseFloat(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $parsed = is_numeric($value) ? (float) $value : $fallback;

        return $parsed > 0 ? $parsed : $fallback;
    }

    private function parseInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = is_numeric($value) ? (int) $value : null;

        return $parsed !== null && $parsed > 0 ? $parsed : null;
    }
}
