<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Eloquent\Builder;

final class WorkboardAttentionInventoryQuery
{
    public static function label(string $attention): ?string
    {
        return match ($attention) {
            'customer_waiting' => 'Customer waiting queue',
            'needs_attention' => 'Needs attention queue',
            default => null,
        };
    }

    public static function apply(Builder $repairOrders, string $attention): Builder
    {
        return match ($attention) {
            'customer_waiting' => self::customerWaiting($repairOrders),
            'needs_attention' => self::needsAttention($repairOrders),
            default => $repairOrders,
        };
    }

    public static function inventoryUrl(string $attention): ?string
    {
        if (self::label($attention) === null) {
            return null;
        }

        return route('operations.repair-orders.index', ['attention' => $attention]);
    }

    private static function customerWaiting(Builder $repairOrders): Builder
    {
        return $repairOrders
            ->whereIn('status', WorkboardSwimlaneCatalog::advisorTriageQueueSlugs())
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('communicationEvents', fn (Builder $events): Builder => $events
                        ->where('event_type', OperationalCommunicationType::EstimateViewed->value))
                    ->orWhereHas('communicationEvents', fn (Builder $events): Builder => $events
                        ->where('event_type', OperationalCommunicationType::CustomerReply->value)
                        ->where('direction', OperationalCommunicationDirection::Inbound->value));
            });
    }

    private static function needsAttention(Builder $repairOrders): Builder
    {
        $stalePickupBefore = now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS);

        return $repairOrders
            ->whereIn('status', WorkboardSwimlaneCatalog::advisorTriageQueueSlugs())
            ->where(function (Builder $query) use ($stalePickupBefore): void {
                $query
                    ->whereHas(
                        'communicationEvents',
                        fn (Builder $events): Builder => $events
                            ->where('event_type', OperationalCommunicationType::EstimateViewed->value),
                        '>=',
                        2,
                    )
                    ->orWhere(function (Builder $shopFloor): Builder {
                        return $shopFloor
                            ->whereIn('status', WorkboardSwimlaneCatalog::shopFloorSlugs())
                            ->whereNull('assigned_technician_id');
                    })
                    ->orWhere(function (Builder $pickup) use ($stalePickupBefore): Builder {
                        return $pickup
                            ->whereIn('status', [
                                RepairOrderStatus::Completed->value,
                                RepairOrderStatus::Invoiced->value,
                                RepairOrderStatus::ReadyPickup->value,
                            ])
                            ->where('updated_at', '<', $stalePickupBefore);
                    });
            });
    }
}
