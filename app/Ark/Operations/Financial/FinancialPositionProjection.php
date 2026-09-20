<?php

namespace App\Ark\Operations\Financial;

use Brick\Money\Money;

/**
 * Disposable projection — owns nothing, answers everything.
 *
 * Only public API for "what does the customer owe?"
 * Construct once. Throw away. Never persist.
 */
final readonly class FinancialPositionProjection
{
    public function __construct(
        public FinancialContractSource $contractSource,
        public int $approvedWorkCents,
        public int $coverageCents,
        public int $customerResponsibilityCents,
        public int $depositsCents,
        public int $paymentsCents,
        public int $creditsCents,
        public int $customerOwesTodayCents,
    ) {}

    public static function for(\App\Ark\Operations\RepairOrders\RepairOrder $repairOrder): self
    {
        return app(FinancialPositionCalculator::class)->for($repairOrder);
    }

    public function hasIssuedFinalInvoice(): bool
    {
        return $this->contractSource === FinancialContractSource::Invoice;
    }

    public function formatCents(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }

    public function projectedBalanceLabel(): string
    {
        return $this->formatCents($this->customerOwesTodayCents);
    }

    /**
     * @return array{
     *     contract_source: string,
     *     approved_work_cents: int,
     *     coverage_cents: int,
     *     customer_responsibility_cents: int,
     *     deposits_cents: int,
     *     payments_cents: int,
     *     credits_cents: int,
     *     customer_owes_today_cents: int,
     *     projected_balance_label: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'contract_source' => $this->contractSource->value,
            'approved_work_cents' => $this->approvedWorkCents,
            'coverage_cents' => $this->coverageCents,
            'customer_responsibility_cents' => $this->customerResponsibilityCents,
            'deposits_cents' => $this->depositsCents,
            'payments_cents' => $this->paymentsCents,
            'credits_cents' => $this->creditsCents,
            'customer_owes_today_cents' => $this->customerOwesTodayCents,
            'projected_balance_label' => $this->projectedBalanceLabel(),
        ];
    }
}
