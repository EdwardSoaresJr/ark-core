<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\CreateCustomerDepositPayTokenAction;
use App\Ark\Operations\Payments\CustomerPayTokenResult;
use App\Ark\Operations\Payments\SquareConfiguration;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Brick\Money\Money;
use RuntimeException;

final class DepositPortalLinkContext
{
    public function __construct(
        private readonly SquareConfiguration $square,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
        private readonly CreateCustomerDepositPayTokenAction $depositTokens,
    ) {}

    /**
     * Soft max for advisor-entered deposit requests (not the counter suggested-deposit guard).
     */
    public function maxAmountCents(RepairOrder $repairOrder): int
    {
        return $this->depositGuard->remainingAllowedDepositCents($repairOrder);
    }

    public function suggestedAmountDecimal(RepairOrder $repairOrder): ?string
    {
        $remaining = $this->depositGuard->remainingSuggestedDepositCents($repairOrder);

        if ($remaining !== null && $remaining > 0) {
            return number_format($remaining / 100, 2, '.', '');
        }

        $collectable = $this->depositGuard->remainingCollectableDepositCents($repairOrder);

        if ($collectable > 0) {
            return number_format($collectable / 100, 2, '.', '');
        }

        return null;
    }

    /**
     * @return array{token: CustomerPayTokenResult, url: string, amount_cents: int, amount_display: string}
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $amountCents): array
    {
        $repairOrder->loadMissing('customer');

        if (! $this->square->portalPayEnabled()) {
            throw new RuntimeException('Customer portal payments are not enabled.');
        }

        if ($repairOrder->isTerminal()) {
            throw new RuntimeException('Closed repair orders cannot send deposit requests.');
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($balance->hasIssuedInvoice) {
            throw new RuntimeException('Use Send Pay Link after the final invoice is issued.');
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('Deposit amount must be greater than zero.');
        }

        $maxCents = $this->maxAmountCents($repairOrder);

        if ($maxCents <= 0) {
            throw new RuntimeException('Nothing left to collect as a deposit.');
        }

        if ($amountCents > $maxCents) {
            throw new RuntimeException(sprintf(
                'Deposit amount cannot exceed %s.',
                Money::ofMinor($maxCents, 'USD')->formatTo('en_US'),
            ));
        }

        $token = $this->depositTokens->execute($repairOrder, $amountCents);
        $url = route('portal.invoice-pay.show', ['token' => $token->plainToken]);
        $amountDisplay = Money::ofMinor($amountCents, 'USD')->formatTo('en_US');

        return [
            'token' => $token,
            'url' => $url,
            'amount_cents' => $amountCents,
            'amount_display' => $amountDisplay,
        ];
    }
}
