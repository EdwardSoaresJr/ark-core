<?php

namespace App\Ark\Operations\Parts;

/**
 * Effective customer-facing part presentation for one document/concern.
 *
 * Shop policy is the base. Billing-class document profiles may still unlock
 * manufacturer number / supplier for audit programs (warranty, RepairPal).
 */
final readonly class CustomerPartPresentationPolicy
{
    public function __construct(
        public CustomerPartDescriptionMode $descriptionMode,
        public bool $showManufacturerNumber,
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
            showSupplier: $this->showSupplier,
            showSupplierSku: $this->showSupplierSku,
            allowDescriptionOverride: $this->allowDescriptionOverride,
            labelsLocked: $locked,
        );
    }
}
