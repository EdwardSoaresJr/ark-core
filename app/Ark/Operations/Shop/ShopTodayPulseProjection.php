<?php

namespace App\Ark\Operations\Shop;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Observations\OperationalObservationStream;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;

/**
 * Lightweight shop-day pulse for continuity surfaces — not reporting authority.
 */
final class ShopTodayPulseProjection
{
    public function __construct(
        private readonly OperationalObservationStream $observationStream,
        private readonly MobileStaffAccess $access,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forUser(User $user): ?array
    {
        if (! $this->access->canViewShopAttention($user)) {
            return null;
        }

        $openRepairOrders = RepairOrder::query()
            ->whereNotIn('status', [
                RepairOrderStatus::Closed->value,
            ])
            ->count();

        $waitingCount = $this->observationStream->activeCount(null);

        $summary = match (true) {
            $waitingCount > 0 && $openRepairOrders > 0 => "{$waitingCount} need attention · {$openRepairOrders} open ROs",
            $waitingCount > 0 => "{$waitingCount} need attention",
            $openRepairOrders > 0 => "{$openRepairOrders} open ROs",
            default => 'Clear today',
        };

        return [
            'open_repair_orders' => $openRepairOrders,
            'waiting_count' => $waitingCount,
            'summary' => $summary,
        ];
    }
}
