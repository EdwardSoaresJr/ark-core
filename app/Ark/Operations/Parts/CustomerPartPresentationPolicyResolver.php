<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Settings\ShopSettings;

final class CustomerPartPresentationPolicyResolver
{
    public function __construct(
        private readonly CustomerPartPresentationProfileResolver $profileResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $concern
     */
    public function forConcern(array $snapshot, array $concern): CustomerPartPresentationPolicy
    {
        $presentation = $this->profileResolver->forConcern($snapshot, $concern);
        $settings = $this->settingsFromSnapshot($snapshot);
        $locked = (bool) ($snapshot['customer_part_labels_locked'] ?? false);

        return $this->fromSettingsAndPresentation($settings, $presentation)->withLabelsLocked($locked);
    }

    public function forShop(
        ?ShopSettings $settings = null,
        ?CustomerDocumentPresentation $presentation = null,
    ): CustomerPartPresentationPolicy {
        $settings ??= ShopSettings::current();
        $presentation ??= new CustomerDocumentPresentation;

        return $this->fromSettingsAndPresentation($settings, $presentation);
    }

    public function fromSettingsAndPresentation(
        ShopSettings $settings,
        CustomerDocumentPresentation $presentation,
    ): CustomerPartPresentationPolicy {
        return new CustomerPartPresentationPolicy(
            descriptionMode: $settings->customerPartDescriptionMode(),
            // Billing-class checkboxes are authoritative. Shop-wide toggles remain
            // an optional floor so existing global enables are not silently removed.
            showManufacturerNumber: $presentation->showPartNumber
                || $settings->customerPartShowManufacturerNumber(),
            showBrand: $presentation->showPartBrand,
            showSupplier: $presentation->showVendor
                || $settings->customerPartShowSupplier(),
            showSupplierSku: $settings->customerPartShowSupplierSku(),
            allowDescriptionOverride: $settings->customerPartAllowDescriptionOverride(),
        );
    }

    /**
     * @deprecated Use {@see fromSettingsAndPresentation()}.
     */
    public function fromSettingsAndProfile(
        ShopSettings $settings,
        CustomerPartPresentationProfile $profile,
    ): CustomerPartPresentationPolicy {
        return $this->fromSettingsAndPresentation(
            $settings,
            CustomerDocumentPresentation::fromLegacyProfile($profile),
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function settingsFromSnapshot(array $snapshot): ShopSettings
    {
        $settingsPayload = $snapshot['settings'] ?? null;

        if (is_array($settingsPayload) && $settingsPayload !== []) {
            $settings = new ShopSettings;
            $settings->forceFill($settingsPayload);

            return $settings;
        }

        return ShopSettings::current();
    }
}
