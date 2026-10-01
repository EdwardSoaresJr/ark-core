<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderShopReference;

/**
 * Parts catalog via Platform. Bound only when ARK_PARTS_CATALOG_DRIVER=platform.
 * Stock Core remains NotConfiguredPartsCatalogLauncher.
 */
final class PlatformPartsCatalogLauncher implements PartsCatalogLauncher
{
    public function __construct(
        private readonly ArkPartsCatalogClient $client,
    ) {}

    public function configured(): bool
    {
        $result = $this->client->readiness();

        return ($result['ok'] ?? false) === true
            && (($result['body']['ready'] ?? false) === true);
    }

    public function poNumber(RepairOrder $repairOrder): string
    {
        return RepairOrderShopReference::purchaseOrderNumber($repairOrder);
    }

    public function blockedReason(RepairOrder $repairOrder): string
    {
        if ($this->configured()) {
            return 'Parts catalog is temporarily unavailable.';
        }

        return 'Parts catalog is not configured for this shop.';
    }
}
