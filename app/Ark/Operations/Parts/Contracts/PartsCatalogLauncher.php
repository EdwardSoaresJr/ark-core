<?php

namespace App\Ark\Operations\Parts\Contracts;

use App\Ark\Operations\RepairOrders\RepairOrder;

interface PartsCatalogLauncher
{
    public function configured(): bool;

    public function poNumber(RepairOrder $repairOrder): string;

    public function blockedReason(RepairOrder $repairOrder): string;
}
