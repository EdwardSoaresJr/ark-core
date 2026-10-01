<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderShopReference;

final class NotConfiguredPartsCatalogLauncher implements PartsCatalogLauncher
{
    public function configured(): bool
    {
        return false;
    }

    public function poNumber(RepairOrder $repairOrder): string
    {
        return RepairOrderShopReference::purchaseOrderNumber($repairOrder);
    }

    public function blockedReason(RepairOrder $repairOrder): string
    {
        return 'Parts catalog is not configured.';
    }
}
