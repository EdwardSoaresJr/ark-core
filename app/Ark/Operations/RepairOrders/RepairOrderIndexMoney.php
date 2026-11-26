<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Support\Collection;

/**
 * Money for the current Repair Orders index page only: batched balances for
 * invoiced / ready-for-pickup ROs, otherwise the read-only estimate total.
 */
final class RepairOrderIndexMoney
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly BalanceDueCalculator $balanceDueCalculator,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, array{label: string, caption: ?string}>
     */
    public function forPage(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $repairOrders->loadMissing(['lines.concern']);

        $balances = $this->balanceDueCalculator->mapForRepairOrders(
            $repairOrders->filter(fn (RepairOrder $repairOrder): bool => in_array(
                $repairOrder->status,
                [RepairOrderStatus::Invoiced, RepairOrderStatus::ReadyPickup],
                true,
            )),
        );

        $money = [];

        foreach ($repairOrders as $repairOrder) {
            $balance = $balances[$repairOrder->id] ?? null;

            if ($balance !== null && $balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
                $money[$repairOrder->id] = ['label' => $this->dollars($balance->balanceDueCents), 'caption' => 'due'];

                continue;
            }

            $money[$repairOrder->id] = [
                'label' => $this->dollars($this->totalsCalculator->totalsFor($repairOrder)->totalCents()),
                'caption' => null,
            ];
        }

        return $money;
    }

    private function dollars(int $cents): string
    {
        return '$'.number_format($cents / 100, 0);
    }
}
