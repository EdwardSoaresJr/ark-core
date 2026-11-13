<?php

namespace App\Ark\Operations\Parts;

/**
 * Effective customer-facing part presentation for one document/concern.
 *
 * Shop description mode is the base. Billing-class document checkboxes unlock
 * part number, brand, and vendor/supplier independently.
 */
final readonly class CustomerPartPresentationPolicy
{
    public function __construct(
        public CustomerPartDescriptionMode $descriptionMode,
        public bool $showManufacturerNumber,
        public bool $showBrand,
        public bool $showSupplier,
        public bool $showSupplierSku,
        public bool $allowDescriptionOverride,
        public bool $labelsLocked = false,
    ) {}

    public function withLabelsLocked(bool $locked = true): self
    {
        return new self(
            descriptionMode: $this->descriptionMode,
            showManufacturerNumber: $this->showManufacturerNumber,
            showBrand: $this->showBrand,
            showSupplier: $this->showSupplier,
            showSupplierSku: $this->showSupplierSku,
            allowDescriptionOverride: $this->allowDescriptionOverride,
            labelsLocked: $locked,
        );
    }
}
