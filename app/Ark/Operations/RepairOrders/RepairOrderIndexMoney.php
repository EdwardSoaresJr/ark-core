<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Page-scoped money for the Repair Orders index.
 *
 * Invoice issuance and settlement come from BalanceDueCalculator (documents +
 * ledger), never from RO status alone. Estimate totals are presentation-only
 * and are never labeled as currently collectible.
 */
final class RepairOrderIndexMoney
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly BalanceDueCalculator $balanceDueCalculator,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, array{label: string, caption: ?string, collectible: bool}>
     */
    public function forPage(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $page = $repairOrders instanceof EloquentCollection
            ? $repairOrders
            : EloquentCollection::make($repairOrders->all());
        $page->loadMissing(['lines.concern']);

        $balances = $this->balanceDueCalculator->mapForRepairOrders($page);
        $money = [];

        foreach ($page as $repairOrder) {
            $money[$repairOrder->id] = $this->present(
                $repairOrder,
                $balances[$repairOrder->id] ?? null,
            );
        }

        return $money;
    }

    /**
     * @return array{label: string, caption: ?string, collectible: bool}
     */
    private function present(RepairOrder $repairOrder, ?BalanceDueResult $balance): array
    {
        if ($balance instanceof BalanceDueResult && $balance->hasIssuedInvoice) {
            if ($balance->balanceDueCents > 0) {
                $onAccount = $repairOrder->isTerminal()
                    || $repairOrder->close_variant_key === 'on_account';

                return [
                    'label' => $this->dollars($balance->balanceDueCents),
                    'caption' => $onAccount ? 'On account' : 'Balance due',
                    'collectible' => true,
                ];
            }

            return [
                'label' => $this->dollars($balance->invoiceTotalCents),
                'caption' => 'Paid',
                'collectible' => false,
            ];
        }

        $estimateCents = $this->totalsCalculator->totalsFor($repairOrder)->totalCents();
        $depositCents = $balance?->unappliedDepositsCents ?? 0;

        if ($estimateCents <= 0 && $depositCents <= 0) {
            return [
                'label' => '—',
                'caption' => null,
                'collectible' => false,
            ];
        }

        if ($estimateCents <= 0 && $depositCents > 0) {
            return [
                'label' => $this->dollars($depositCents),
                'caption' => 'Deposit on file',
                'collectible' => false,
            ];
        }

        if ($depositCents > 0) {
            return [
                'label' => $this->dollars($estimateCents),
                'caption' => 'Estimate · deposit on file',
                'collectible' => false,
            ];
        }

        return [
            'label' => $this->dollars($estimateCents),
            'caption' => 'Estimate',
            'collectible' => false,
        ];
    }

    private function dollars(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }
}
