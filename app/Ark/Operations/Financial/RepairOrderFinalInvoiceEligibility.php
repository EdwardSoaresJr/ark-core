<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

/**
 * Operational statuses where a final invoice may be issued.
 *
 * Money truth remains the invoice document + ledger. This is not a substitute
 * for authorization or balance checks — only the visit-lifecycle gate.
 */
final class RepairOrderFinalInvoiceEligibility
{
    /**
     * @return list<RepairOrderStatus>
     */
    public static function allowedStatuses(): array
    {
        return [
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ];
    }

    public static function statusAllows(RepairOrder|RepairOrderStatus|string $repairOrderOrStatus): bool
    {
        $slug = match (true) {
            $repairOrderOrStatus instanceof RepairOrder => $repairOrderOrStatus->status->value,
            $repairOrderOrStatus instanceof RepairOrderStatus => $repairOrderOrStatus->value,
            default => RepairOrderStatus::fromSlug((string) $repairOrderOrStatus)->value,
        };

        return in_array(
            $slug,
            array_map(static fn (RepairOrderStatus $status): string => $status->value, self::allowedStatuses()),
            true,
        );
    }

    public static function blockingReason(RepairOrder $repairOrder): ?string
    {
        if (self::statusAllows($repairOrder)) {
            return null;
        }

        return 'Move the repair order to Completed or Ready for Pickup before issuing the final invoice. Deposits and advance payments are still available now.';
    }
}
