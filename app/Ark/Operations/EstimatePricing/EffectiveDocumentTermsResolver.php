<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Resolves shop + per-concern document terms for estimates and invoices.
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
        $global = $settings->globalDocumentDisclaimerFor($documentType);
        $authorization = filled($settings->authorizationLanguage())
            ? str_replace(
                '{shop_name}',
                filled($settings->shop_name) ? trim((string) $settings->shop_name) : (string) config('app.name'),
                $settings->authorizationLanguage(),
            )
            : null;

        $concernTerms = [];
        foreach ($repairOrder->concerns as $concern) {
            if (! $concern instanceof RepairOrderConcern) {
                continue;
            }
            $concernTerms[] = $this->forConcern($concern, $settings);
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
    public function forConcern(RepairOrderConcern $concern, ?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $classKey = $concern->effectiveBillingClassKey();
        $row = $settings->customerTypeRowByKeyOrName($classKey)
            ?? $settings->customerTypeRowByKeyOrName(null, $concern->billingClassLabel());

        $mode = BillingClassDocumentTermsMode::tryFromMixed($row['document_terms_mode'] ?? null);
        $terms = filled($row['document_terms'] ?? null)
            ? trim((string) $row['document_terms'])
            : $settings->customerTypeDocumentDisclaimerFor($row['name'] ?? $row['key'] ?? null);

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
}
