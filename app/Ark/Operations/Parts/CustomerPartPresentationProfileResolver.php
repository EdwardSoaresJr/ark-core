<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
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

        $commercial = CommercialPricingProfile::tryFromStored(
            is_array(data_get($snapshot, 'commercial_pricing_profile'))
                ? data_get($snapshot, 'commercial_pricing_profile')
                : (is_array(data_get($snapshot, 'pricing.commercial_pricing_profile'))
                    ? data_get($snapshot, 'pricing.commercial_pricing_profile')
                    : null),
        );

        if ($commercial !== null) {
            return $commercial->documentPresentationProfileFor(is_string($customerType) ? $customerType : null);
        }

        // Prefer snapshot-frozen presentation when present (issued/open docs).
        $frozen = data_get($snapshot, 'customer.document_presentation_profile')
            ?? data_get($snapshot, 'document_presentation_profile');
        if (filled($frozen)) {
            return CustomerPartPresentationProfile::fromStored((string) $frozen);
        }

        return ShopSettings::current()->documentPresentationProfileFor(
            is_string($customerType) ? $customerType : null,
        );
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
