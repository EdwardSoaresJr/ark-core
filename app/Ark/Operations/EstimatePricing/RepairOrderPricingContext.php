<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Authoritative pricing inputs for one repair order.
 *
 * Specialized profiles (labor / shop fee / commercial) remain the storage shape;
 * this context is the single resolution entry point so calculators never silently
 * mix frozen RO rules with today's ShopSettings for an existing RO.
 *
 * Legacy null profiles:
 * - Living RO: may still resolve commercial/fee from live settings until stamped
 *   (transition window before the next settings activation).
 * - Terminal RO: never falls back to live commercial/fee/labor rules that would
 *   reprice stored line amounts — callers must treat stored line cents as truth.
 */
final class RepairOrderPricingContext
{
    public function __construct(
        public readonly RepairOrder $repairOrder,
        public readonly ShopSettings $shopSettings,
        public readonly ?LaborPricingProfile $labor,
        public readonly ?ShopFeeProfile $shopFee,
        public readonly ?CommercialPricingProfile $commercial,
        public readonly bool $terminal,
        public readonly bool $allowsLiveCommercialFallback,
    ) {}

    public static function for(RepairOrder $repairOrder, ?ShopSettings $settings = null): self
    {
        $settings ??= ShopSettings::current();
        $terminal = $repairOrder->isTerminal();

        $labor = LaborPricingProfile::tryFromStored(
            is_array($repairOrder->labor_pricing_profile) ? $repairOrder->labor_pricing_profile : null,
        );
        $shopFee = ShopFeeProfile::tryFromStored(
            is_array($repairOrder->shop_fee_profile) ? $repairOrder->shop_fee_profile : null,
        );
        $commercial = CommercialPricingProfile::tryFromStored(
            is_array($repairOrder->commercial_pricing_profile) ? $repairOrder->commercial_pricing_profile : null,
        );

        // Terminal + missing profile: do not invent historical rules from live settings.
        $allowsLiveCommercialFallback = ! $terminal;

        if ($commercial === null && $allowsLiveCommercialFallback) {
            $commercial = CommercialPricingProfile::fromShopSettings($settings);
        }

        if ($shopFee === null && $allowsLiveCommercialFallback) {
            $shopFee = ShopFeeProfile::fromShopSettings($settings);
        }

        return new self(
            repairOrder: $repairOrder,
            shopSettings: $settings,
            labor: $labor,
            shopFee: $shopFee,
            commercial: $commercial,
            terminal: $terminal,
            allowsLiveCommercialFallback: $allowsLiveCommercialFallback,
        );
    }

    public function shopFeeProfile(): ShopFeeProfile
    {
        return $this->shopFee ?? ShopFeeProfile::fromShopSettings($this->shopSettings);
    }

    public function commercialProfile(): ?CommercialPricingProfile
    {
        return $this->commercial;
    }

    /**
     * @return array{type: string, percent: string}|null
     */
    public function standingDiscountPolicy(?string $customerType): ?array
    {
        if ($this->commercial === null) {
            // Terminal legacy: no live discount policy — preserve stored line discounts.
            return null;
        }

        return $this->commercial->standingDiscountPolicy($customerType);
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForCustomerType(?string $customerType): array
    {
        if ($this->commercial === null) {
            return ['enabled' => false, 'rate' => null];
        }

        return $this->commercial->shopFeePolicyForCustomerType($customerType);
    }

    public function documentPresentationProfileFor(?string $customerType): CustomerPartPresentationProfile
    {
        if ($this->commercial !== null) {
            return $this->commercial->documentPresentationProfileFor($customerType);
        }

        if (! $this->allowsLiveCommercialFallback) {
            return CustomerPartPresentationProfile::Retail;
        }

        return $this->shopSettings->documentPresentationProfileFor($customerType);
    }

    /**
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}|null
     */
    public function partsMatrixByKey(?string $key): ?array
    {
        if ($this->commercial !== null) {
            $fromStamp = $this->commercial->partsMatrixByKey($key);
            if ($fromStamp !== null) {
                return $fromStamp;
            }
        }

        if (! $this->allowsLiveCommercialFallback) {
            return null;
        }

        return $this->shopSettings->partsMatrixByKey($key);
    }

    /**
     * Provenance bag for future explainability surfaces (no second pricing system).
     *
     * @return array{
     *     repair_order_id: int|null,
     *     terminal: bool,
     *     allows_live_commercial_fallback: bool,
     *     labor_schema: int|null,
     *     shop_fee_schema: int|null,
     *     commercial_schema: int|null,
     *     commercial_version: int|null
     * }
     */
    public function provenance(): array
    {
        return [
            'repair_order_id' => $this->repairOrder->id,
            'terminal' => $this->terminal,
            'allows_live_commercial_fallback' => $this->allowsLiveCommercialFallback,
            'labor_schema' => $this->labor?->schema,
            'shop_fee_schema' => $this->shopFee?->schema,
            'commercial_schema' => $this->commercial?->schema,
            'commercial_version' => $this->commercial?->version,
        ];
    }
}
