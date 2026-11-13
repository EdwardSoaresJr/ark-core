<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Resolves which Billing Class owns document presentation for a concern, then
 * loads that class's checkbox settings. Concern billing posture never hard-codes
 * Warranty/RepairPal field combinations — it only selects the applicable class.
 */
final class CustomerPartPresentationProfileResolver
{
    public function forConcern(array $snapshot, array $concern): CustomerDocumentPresentation
    {
        $billingClassName = $this->applicableBillingClassName($snapshot, $concern);

        return $this->presentationForBillingClass($snapshot, $billingClassName);
    }

    /**
     * Legacy label for audit/telemetry on concerns. Prefer {@see forConcern()}.
     */
    public function legacyProfileLabel(CustomerDocumentPresentation $presentation): string
    {
        return match (true) {
            $presentation->showPartNumber && $presentation->showVendor => CustomerPartPresentationProfile::Warranty->value,
            $presentation->showPartNumber && ! $presentation->showVendor => CustomerPartPresentationProfile::RepairPal->value,
            default => CustomerPartPresentationProfile::Retail->value,
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function presentationForBillingClass(array $snapshot, ?string $billingClassName): CustomerDocumentPresentation
    {
        $commercial = $this->commercialProfile($snapshot);

        if ($commercial !== null) {
            return $commercial->documentPresentationFor($billingClassName);
        }

        // Prefer snapshot-frozen presentation when present (issued/open docs).
        $frozen = CustomerDocumentPresentation::fromFrozen(
            is_array(data_get($snapshot, 'customer.document_presentation'))
                ? data_get($snapshot, 'customer.document_presentation')
                : (is_array(data_get($snapshot, 'document_presentation'))
                    ? data_get($snapshot, 'document_presentation')
                    : null),
            data_get($snapshot, 'customer.document_presentation_profile')
                ?? data_get($snapshot, 'document_presentation_profile'),
        );

        if ($frozen !== null && $this->frozenMatchesCustomerClass($snapshot, $billingClassName)) {
            return $frozen;
        }

        return ShopSettings::current()->documentPresentationFor($billingClassName);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $concern
     */
    private function applicableBillingClassName(array $snapshot, array $concern): ?string
    {
        $posture = ConcernBillingPosture::fromStored($concern['billing_posture'] ?? null);
        $fromPosture = $posture->billingClassName();

        if ($fromPosture !== null) {
            return $fromPosture;
        }

        $customerType = data_get($snapshot, 'customer.customer_type');

        return is_string($customerType) && $customerType !== '' ? $customerType : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function commercialProfile(array $snapshot): ?CommercialPricingProfile
    {
        return CommercialPricingProfile::tryFromStored(
            is_array(data_get($snapshot, 'commercial_pricing_profile'))
                ? data_get($snapshot, 'commercial_pricing_profile')
                : (is_array(data_get($snapshot, 'pricing.commercial_pricing_profile'))
                    ? data_get($snapshot, 'pricing.commercial_pricing_profile')
                    : null),
        );
    }

    /**
     * Customer-level frozen presentation applies only when the concern uses the
     * customer's billing class (not a different posture-mapped class).
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function frozenMatchesCustomerClass(array $snapshot, ?string $billingClassName): bool
    {
        $customerType = data_get($snapshot, 'customer.customer_type');

        if (! is_string($customerType) || $customerType === '' || $billingClassName === null) {
            return true;
        }

        return strcasecmp($customerType, $billingClassName) === 0;
    }
}
