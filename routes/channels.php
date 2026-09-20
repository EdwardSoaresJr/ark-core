<?php

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('operations.repair-orders.{repairOrderId}', function ($user, int $repairOrderId): bool {
    if (! $user->can(ArkCapability::RepairOrdersView->value)) {
        return false;
    }

    return RepairOrder::query()
        ->where('repair_order_id', $repairOrderId)
        ->exists();
});

Broadcast::channel('operations.incoming-calls', function ($user): bool {
    return $user->can(ArkCapability::OperationsAccess->value);
});

Broadcast::channel('operations.conversations', function ($user): bool {
    return $user->can(ArkCapability::OperationsAccess->value);
});

Broadcast::channel('operations.comms-interrupts', function ($user): bool {
    return $user->can(ArkCapability::OperationsAccess->value);
});
