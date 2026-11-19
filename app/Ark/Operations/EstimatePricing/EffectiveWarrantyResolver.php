<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Resolves effective warranty for a concern: override → class mode → shop default.
 *
 * Class and shop warranty come from the RO commercial stamp when present so open ROs
 * do not inherit later Shop Settings edits. Concern-level overrides remain authoritative.
 */
final class EffectiveWarrantyResolver
{
    /**
     * @return array{
     *     source: 'override'|'class'|'shop'|'none',
     *     inherits: bool,
     *     mode: string,
     *     warranty: array<string, mixed>,
     *     summary: string,
     *     third_party_disclaimer: string|null
     * }
     */
    public function forConcern(RepairOrderConcern $concern, ?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $inherits = $concern->inheritsClassWarranty();

        if (! $inherits) {
            $override = BillingClassWarranty::fromArray($concern->warrantyOverridePayload());

            return $this->present('override', false, BillingClassWarrantyMode::ClassSpecific, $override);
        }

        $context = $this->pricingContextFor($concern, $settings);
        $row = $this->classRow($concern, $context, $settings);

        return $this->forClassRow($row, $settings, $context);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{
     *     source: 'override'|'class'|'shop'|'none',
     *     inherits: bool,
     *     mode: string,
     *     warranty: array<string, mixed>,
     *     summary: string,
     *     third_party_disclaimer: string|null
     * }
     */
    public function forClassRow(
        ?array $row,
        ?ShopSettings $settings = null,
        ?RepairOrderPricingContext $context = null,
    ): array {
        $settings ??= ShopSettings::current();
        $mode = BillingClassWarrantyMode::tryFromMixed($row['warranty_mode'] ?? null);

        return match ($mode) {
            BillingClassWarrantyMode::None => $this->present('none', true, $mode, BillingClassWarranty::none()),
            BillingClassWarrantyMode::ClassSpecific => $this->present(
                'class',
                true,
                $mode,
                BillingClassWarranty::fromArray(is_array($row['warranty'] ?? null) ? $row['warranty'] : null),
            ),
            BillingClassWarrantyMode::InheritShop => $this->present(
                'shop',
                true,
                $mode,
                $this->shopWarranty($context, $settings),
            ),
        };
    }

    private function pricingContextFor(RepairOrderConcern $concern, ShopSettings $settings): RepairOrderPricingContext
    {
        $repairOrder = $concern->repairOrder;
        if (! $repairOrder instanceof RepairOrder) {
            $concern->loadMissing('repairOrder');
            $repairOrder = $concern->repairOrder;
        }

        if (! $repairOrder instanceof RepairOrder) {
            return RepairOrderPricingContext::for(new RepairOrder, $settings);
        }

        return RepairOrderPricingContext::for($repairOrder, $settings);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function classRow(
        RepairOrderConcern $concern,
        RepairOrderPricingContext $context,
        ShopSettings $settings,
    ): ?array {
        $classKey = $concern->effectiveBillingClassKey();
        $label = $concern->billingClassLabel();
        $commercial = $context->commercialProfile();
        $roHasStamp = is_array($context->repairOrder->commercial_pricing_profile)
            && $context->repairOrder->commercial_pricing_profile !== [];

        if ($roHasStamp && $commercial !== null) {
            return $commercial->customerTypeRowByKeyOrName($classKey, $label);
        }

        if (! $context->allowsLiveCommercialFallback) {
            return null;
        }

        if ($commercial !== null) {
            $fromCommercial = $commercial->customerTypeRowByKeyOrName($classKey, $label);
            if ($fromCommercial !== null) {
                return $fromCommercial;
            }
        }

        return $settings->customerTypeRowByKeyOrName($classKey)
            ?? $settings->customerTypeRowByKeyOrName(null, $label);
    }

    private function shopWarranty(?RepairOrderPricingContext $context, ShopSettings $settings): BillingClassWarranty
    {
        $commercial = $context?->commercialProfile();
        $roHasStamp = $context !== null
            && is_array($context->repairOrder->commercial_pricing_profile)
            && $context->repairOrder->commercial_pricing_profile !== [];

        if ($roHasStamp && $commercial !== null) {
            // Stamped RO: never invent shop warranty from later live settings.
            return $commercial->hasDocumentTermsStamp
                ? $commercial->shopWarranty()
                : BillingClassWarranty::none();
        }

        if ($context !== null && ! $context->allowsLiveCommercialFallback) {
            return BillingClassWarranty::none();
        }

        if ($commercial?->hasDocumentTermsStamp) {
            return $commercial->shopWarranty();
        }

        return $settings->shopWarranty();
    }

    /**
     * @return array{
     *     source: 'override'|'class'|'shop'|'none',
     *     inherits: bool,
     *     mode: string,
     *     warranty: array<string, mixed>,
     *     summary: string,
     *     third_party_disclaimer: string|null
     * }
     */
    private function present(
        string $source,
        bool $inherits,
        BillingClassWarrantyMode $mode,
        BillingClassWarranty $warranty,
    ): array {
        $thirdPartyDisclaimer = null;
        if ($warranty->thirdParty || ($source === 'class' && $mode === BillingClassWarrantyMode::ClassSpecific && $warranty->thirdParty)) {
            $thirdPartyDisclaimer = 'Coverage is administered by a third party. The shop does not guarantee third-party warranty coverage.';
        }

        if ($mode === BillingClassWarrantyMode::None || ($source === 'none')) {
            return [
                'source' => 'none',
                'inherits' => $inherits,
                'mode' => BillingClassWarrantyMode::None->value,
                'warranty' => BillingClassWarranty::none()->toArray(),
                'summary' => 'No warranty',
                'third_party_disclaimer' => null,
            ];
        }

        return [
            'source' => $source,
            'inherits' => $inherits,
            'mode' => $mode->value,
            'warranty' => $warranty->toArray(),
            'summary' => $warranty->isEmpty() ? 'No warranty details configured' : $warranty->summaryLabel(),
            'third_party_disclaimer' => $thirdPartyDisclaimer,
        ];
    }
}
