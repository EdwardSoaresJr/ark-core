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
        $profile = $this->profileResolver->forConcern($snapshot, $concern);
        $settings = $this->settingsFromSnapshot($snapshot);
        $locked = (bool) ($snapshot['customer_part_labels_locked'] ?? false);

        return $this->fromSettingsAndProfile($settings, $profile)->withLabelsLocked($locked);
    }

    public function forShop(?ShopSettings $settings = null, ?CustomerPartPresentationProfile $profile = null): CustomerPartPresentationPolicy
    {
        $settings ??= ShopSettings::current();
        $profile ??= CustomerPartPresentationProfile::Retail;

        return $this->fromSettingsAndProfile($settings, $profile);
    }

    public function fromSettingsAndProfile(ShopSettings $settings, CustomerPartPresentationProfile $profile): CustomerPartPresentationPolicy
    {
        return new CustomerPartPresentationPolicy(
            descriptionMode: $settings->customerPartDescriptionMode(),
            showManufacturerNumber: $settings->customerPartShowManufacturerNumber() || $profile->showsPartNumber(),
            showSupplier: $settings->customerPartShowSupplier() || $profile->showsVendor(),
            showSupplierSku: $settings->customerPartShowSupplierSku(),
            allowDescriptionOverride: $settings->customerPartAllowDescriptionOverride(),
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
