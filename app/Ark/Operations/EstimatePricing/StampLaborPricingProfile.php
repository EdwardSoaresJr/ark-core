<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;

final class StampLaborPricingProfile
{
    /**
     * Stamp current shop labor pricing rules onto a newly created RO.
     * No-ops when shop has never activated a profile (version 0) so behavior stays legacy.
     */
    public function stampNewRepairOrder(RepairOrder $repairOrder, ?ShopSettings $settings = null): void
    {
        $settings ??= ShopSettings::current();
        $version = (int) ($settings->labor_pricing_profile_version ?? 0);

        if ($version < 1) {
            return;
        }

        $profile = LaborPricingProfile::fromShopSettings($settings);

        $repairOrder->forceFill([
            'labor_pricing_profile' => $profile->toArray(),
        ])->save();
    }
}
