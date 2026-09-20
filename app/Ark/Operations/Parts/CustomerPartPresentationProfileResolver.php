<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

final class CustomerPartPresentationProfileResolver
{
    public function forConcern(array $snapshot, array $concern): CustomerPartPresentationProfile
    {
        $posture = ConcernBillingPosture::fromStored($concern['billing_posture'] ?? null);
        $fromPosture = $this->profileFromBillingPosture($posture);

        if ($fromPosture !== null) {
            return $fromPosture;
        }

        $customerType = data_get($snapshot, 'customer.customer_type');

        return ShopSettings::current()->documentPresentationProfileFor($customerType);
    }

    private function profileFromBillingPosture(ConcernBillingPosture $posture): ?CustomerPartPresentationProfile
    {
        return match ($posture) {
            ConcernBillingPosture::Warranty, ConcernBillingPosture::WarrantyOther => CustomerPartPresentationProfile::Warranty,
            ConcernBillingPosture::RepairPal, ConcernBillingPosture::WarrantyRepairPal => CustomerPartPresentationProfile::RepairPal,
            ConcernBillingPosture::Fleet => CustomerPartPresentationProfile::Fleet,
            ConcernBillingPosture::Internal,
            ConcernBillingPosture::Wholesale,
            ConcernBillingPosture::Comeback => CustomerPartPresentationProfile::Retail,
            default => null,
        };
    }
}
