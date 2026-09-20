<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

final class RepairOrderDepositRecordingGuard
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
    ) {}

    /**
     * Remaining suggested deposit before another ledger entry would exceed shop policy.
     * Null when default deposit policy is disabled or has no amount.
     *
     * Pass a request-scoped {@see BalanceDueResult} when settlement was already projected
     * (e.g. RO show presenter) so this does not re-query invoice/ledger.
     */
    public function remainingSuggestedDepositCents(
        RepairOrder $repairOrder,
        ?BalanceDueResult $balance = null,
    ): ?int {
        $defaultDeposit = $this->defaultDepositCalculator->forRepairOrder($repairOrder);

        if (! $defaultDeposit->enabled || ! $defaultDeposit->hasAmount()) {
            return null;
        }

        $unappliedDepositsCents = ($balance ?? $this->balanceDue->forRepairOrder($repairOrder))
            ->unappliedDepositsCents;

        return max(0, $defaultDeposit->totalCents - $unappliedDepositsCents);
    }

    public function suggestedDepositSatisfied(
        RepairOrder $repairOrder,
        ?BalanceDueResult $balance = null,
    ): bool {
        $remaining = $this->remainingSuggestedDepositCents($repairOrder, $balance);

        return $remaining !== null && $remaining === 0;
    }

    /**
     * Remaining approved work the customer still owes today (pre-invoice).
     */
    public function remainingCollectableDepositCents(
        RepairOrder $repairOrder,
        ?BalanceDueResult $balance = null,
        ?FinancialPositionProjection $position = null,
    ): int {
        $balance ??= $this->balanceDue->forRepairOrder($repairOrder);

        if ($repairOrder->isTerminal() || $balance->hasIssuedInvoice) {
            return 0;
        }

        $position ??= FinancialPositionProjection::for($repairOrder);

        return max(0, $position->customerOwesTodayCents);
    }

    /**
     * Hard cap for the next deposit:
     * shop suggested remainder first, then remaining owe-today after that is covered.
     */
    public function remainingAllowedDepositCents(
        RepairOrder $repairOrder,
        ?BalanceDueResult $balance = null,
        ?FinancialPositionProjection $position = null,
    ): int {
        $suggestedRemaining = $this->remainingSuggestedDepositCents($repairOrder, $balance);

        if ($suggestedRemaining !== null && $suggestedRemaining > 0) {
            return $suggestedRemaining;
        }

        return $this->remainingCollectableDepositCents($repairOrder, $balance, $position);
    }

    public function portalChargeCents(RepairOrder $repairOrder, int $approvedAmountCents): int
    {
        $allowed = $this->remainingAllowedDepositCents($repairOrder);

        if ($allowed <= 0 || $approvedAmountCents <= 0) {
            return 0;
        }

        $suggestedRemaining = $this->remainingSuggestedDepositCents($repairOrder);

        if ($suggestedRemaining !== null && $suggestedRemaining > 0) {
            $policy = $this->defaultDepositCalculator->portalDepositCents($repairOrder, $approvedAmountCents);

            return min($allowed, $policy);
        }

        return $allowed;
    }

    public function validateAmount(RepairOrder $repairOrder, int $amountCents): void
    {
        $remaining = $this->remainingAllowedDepositCents($repairOrder);

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nothing left to collect as a deposit. The remaining balance is already covered.',
            ]);
        }

        if ($amountCents > $remaining) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Amount exceeds what the customer still owes today (%s).',
                    Money::ofMinor($remaining, 'USD')->formatTo('en_US'),
                ),
            ]);
        }
    }
}
