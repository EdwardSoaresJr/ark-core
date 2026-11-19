<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Resolves shop + per-concern document terms for estimates and invoices.
 *
 * When the RO carries a commercial stamp, class terms and shop disclaimers come from that
 * stamp so later Shop Settings edits cannot rewrite open-RO contractual language.
 */
final class EffectiveDocumentTermsResolver
{
    public function __construct(
        private readonly EffectiveWarrantyResolver $warrantyResolver = new EffectiveWarrantyResolver,
    ) {}

    /**
     * @return array{
     *     global_disclaimer: string,
     *     authorization_language: string|null,
     *     shop_terms_fingerprint: string,
     *     concern_terms: list<array{
     *         concern_id: int,
     *         concern_summary: string,
     *         billing_class_key: string|null,
     *         billing_class_name: string|null,
     *         terms_mode: string,
     *         terms: string|null,
     *         warranty: array<string, mixed>
     *     }>
     * }
     */
    public function forRepairOrder(
        RepairOrder $repairOrder,
        FinancialDocumentType $documentType,
        ?ShopSettings $settings = null,
    ): array {
        $settings ??= ShopSettings::current();
        $context = RepairOrderPricingContext::for($repairOrder, $settings);
        $global = $this->globalDisclaimer($documentType, $context, $settings);
        $authorization = $this->authorizationLanguage($context, $settings);

        $concernTerms = [];
        foreach ($repairOrder->concerns as $concern) {
            if (! $concern instanceof RepairOrderConcern) {
                continue;
            }
            $concern->setRelation('repairOrder', $repairOrder);
            $concernTerms[] = $this->forConcern($concern, $settings, $context);
        }

        return [
            'global_disclaimer' => $global,
            'authorization_language' => $authorization,
            'shop_terms_fingerprint' => hash('sha256', $global.'|'.(string) $authorization),
            'concern_terms' => $concernTerms,
        ];
    }

    /**
     * @return array{
     *     concern_id: int,
     *     concern_summary: string,
     *     billing_class_key: string|null,
     *     billing_class_name: string|null,
     *     terms_mode: string,
     *     terms: string|null,
     *     warranty: array<string, mixed>
     * }
     */
    public function forConcern(
        RepairOrderConcern $concern,
        ?ShopSettings $settings = null,
        ?RepairOrderPricingContext $context = null,
    ): array {
        $settings ??= ShopSettings::current();
        $context ??= $this->pricingContextFor($concern, $settings);
        $classKey = $concern->effectiveBillingClassKey();
        $row = $this->classRow($concern, $context, $settings);

        $mode = BillingClassDocumentTermsMode::tryFromMixed($row['document_terms_mode'] ?? null);
        $terms = filled($row['document_terms'] ?? null)
            ? trim((string) $row['document_terms'])
            : $this->customerTypeDisclaimer($row['name'] ?? $row['key'] ?? null, $context, $settings);

        if ($mode === BillingClassDocumentTermsMode::ShopDefaults) {
            $terms = null;
        }

        return [
            'concern_id' => (int) $concern->id,
            'concern_summary' => (string) $concern->summary,
            'billing_class_key' => is_array($row) ? ($row['key'] ?? null) : null,
            'billing_class_name' => is_array($row) ? ($row['name'] ?? null) : $concern->billingClassLabel(),
            'terms_mode' => $mode->value,
            'terms' => filled($terms) ? $terms : null,
            'warranty' => $this->warrantyResolver->forConcern($concern, $settings),
        ];
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

    private function globalDisclaimer(
        FinancialDocumentType $documentType,
        RepairOrderPricingContext $context,
        ShopSettings $settings,
    ): string {
        $commercial = $context->commercialProfile();
        $roHasStamp = is_array($context->repairOrder->commercial_pricing_profile)
            && $context->repairOrder->commercial_pricing_profile !== [];

        if ($roHasStamp && $commercial !== null) {
            return $commercial->hasDocumentTermsStamp
                ? $commercial->globalDocumentDisclaimerFor($documentType->value)
                : '';
        }

        if (! $context->allowsLiveCommercialFallback) {
            return '';
        }

        if ($commercial?->hasDocumentTermsStamp) {
            return $commercial->globalDocumentDisclaimerFor($documentType->value);
        }

        return $settings->globalDocumentDisclaimerFor($documentType);
    }

    private function authorizationLanguage(RepairOrderPricingContext $context, ShopSettings $settings): ?string
    {
        $commercial = $context->commercialProfile();
        $roHasStamp = is_array($context->repairOrder->commercial_pricing_profile)
            && $context->repairOrder->commercial_pricing_profile !== [];

        $language = null;
        if ($roHasStamp && $commercial !== null) {
            $language = $commercial->hasDocumentTermsStamp ? $commercial->authorizationLanguage : null;
        } elseif ($context->allowsLiveCommercialFallback) {
            $language = $commercial?->hasDocumentTermsStamp
                ? $commercial->authorizationLanguage
                : $settings->authorizationLanguage();
        }

        if (! filled($language)) {
            return null;
        }

        $shopName = filled($settings->shop_name) ? trim((string) $settings->shop_name) : (string) config('app.name');

        return str_replace('{shop_name}', $shopName, trim((string) $language));
    }

    private function customerTypeDisclaimer(
        ?string $customerType,
        RepairOrderPricingContext $context,
        ShopSettings $settings,
    ): ?string {
        $commercial = $context->commercialProfile();
        $roHasStamp = is_array($context->repairOrder->commercial_pricing_profile)
            && $context->repairOrder->commercial_pricing_profile !== [];

        if ($roHasStamp && $commercial !== null) {
            return $commercial->hasDocumentTermsStamp
                ? $commercial->customerTypeDocumentDisclaimerFor($customerType)
                : null;
        }

        if (! $context->allowsLiveCommercialFallback) {
            return null;
        }

        if ($commercial?->hasDocumentTermsStamp) {
            return $commercial->customerTypeDocumentDisclaimerFor($customerType);
        }

        return $settings->customerTypeDocumentDisclaimerFor($customerType);
    }
}
