<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Resolves effective warranty for a concern: override → class mode → shop default.
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

        $classKey = $concern->effectiveBillingClassKey();
        $row = $settings->customerTypeRowByKeyOrName($classKey)
            ?? $settings->customerTypeRowByKeyOrName(null, $concern->billingClassLabel());

        return $this->forClassRow(is_array($row) ? $row : null, $settings);
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
    public function forClassRow(?array $row, ?ShopSettings $settings = null): array
    {
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
                $settings->shopWarranty(),
            ),
        };
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
        if ($warranty->thirdParty || $source === 'class' && $mode === BillingClassWarrantyMode::ClassSpecific && $warranty->thirdParty) {
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
