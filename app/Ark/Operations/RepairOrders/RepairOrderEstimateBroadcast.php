<?php

namespace App\Ark\Operations\RepairOrders;

final class RepairOrderEstimateBroadcast
{
    public static function enabled(): bool
    {
        $connection = config('broadcasting.default');

        return filled($connection) && $connection !== 'null';
    }

    public static function channelName(int $repairOrderId): string
    {
        return 'operations.repair-orders.'.$repairOrderId;
    }
}
