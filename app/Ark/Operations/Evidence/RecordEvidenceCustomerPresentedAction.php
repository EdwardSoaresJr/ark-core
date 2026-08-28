<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Marks first_customer_viewed_at when a customer-facing surface *presents* Shared evidence.
 * Never call from media stream controllers.
 */
final class RecordEvidenceCustomerPresentedAction
{
    /**
     * @param  Collection<int, Evidence>|iterable<Evidence>  $presentedShared
     */
    public function handle(RepairOrder $repairOrder, iterable $presentedShared): void
    {
        $ids = collect($presentedShared)
            ->filter(fn ($row): bool => $row instanceof Evidence)
            ->filter(fn (Evidence $evidence): bool => (int) $evidence->repair_order_id === (int) $repairOrder->id)
            ->filter(fn (Evidence $evidence): bool => $evidence->isCustomerFacing())
            ->filter(fn (Evidence $evidence): bool => $evidence->first_customer_viewed_at === null)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        Evidence::query()
            ->whereIn('id', $ids)
            ->whereNull('first_customer_viewed_at')
            ->where('visibility', EvidenceVisibility::Shared->value)
            ->whereNull('deleted_at')
            ->update(['first_customer_viewed_at' => now()]);
    }
}
