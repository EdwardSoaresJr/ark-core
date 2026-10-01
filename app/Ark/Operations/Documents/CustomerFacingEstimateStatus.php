<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

final class CustomerFacingEstimateStatus
{
    public function labelForRepairOrder(RepairOrder $repairOrder): string
    {
        $repairOrder->loadMissing(['concerns']);

        return $this->labelForVisibleConcerns(
            $repairOrder->concerns
                ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->visibleToCustomer())
                ->values(),
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function labelForSnapshot(array $snapshot): string
    {
        $concerns = collect($snapshot['concerns'] ?? [])
            ->filter(fn (mixed $concern): bool => is_array($concern))
            ->filter(function (array $concern): bool {
                $disposition = RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));

                return $disposition?->visibleToCustomer() ?? false;
            })
            ->values();

        return $this->labelForVisibleConcernArrays($concerns);
    }

    /**
     * @param  iterable<int, RepairOrderConcern>  $concerns
     */
    private function labelForVisibleConcerns(iterable $concerns): string
    {
        return $this->labelForVisibleConcernArrays(
            collect($concerns)->map(fn (RepairOrderConcern $concern): array => [
                'disposition' => $concern->disposition->value,
            ]),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $concerns
     */
    private function labelForVisibleConcernArrays($concerns): string
    {
        if ($concerns->isEmpty()) {
            return 'Estimate';
        }

        if ($concerns->contains(fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Recommended->value)) {
            return 'Awaiting your approval';
        }

        if ($concerns->contains(fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Approved->value)) {
            return 'Approved';
        }

        $declinedCount = $concerns->where('disposition', RepairOrderConcernDisposition::Declined->value)->count();
        $deferredCount = $concerns->where('disposition', RepairOrderConcernDisposition::Deferred->value)->count();

        if ($declinedCount > 0 && $deferredCount === 0) {
            return 'Declined';
        }

        if ($deferredCount > 0 && $declinedCount === 0) {
            return 'Deferred for follow-up';
        }

        if ($declinedCount > 0 || $deferredCount > 0) {
            return 'Response recorded';
        }

        return 'Estimate';
    }
}
