<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\EstimatePricing\BillingClassDocumentTermsMode;
use App\Ark\Operations\EstimatePricing\EffectiveDocumentTermsResolver;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;

final class DocumentDisclaimerComposer
{
    public function __construct(
        private readonly EffectiveDocumentTermsResolver $termsResolver = new EffectiveDocumentTermsResolver,
    ) {}

    /**
     * @return array{
     *     global_disclaimer: string,
     *     customer_type: string|null,
     *     customer_type_disclaimer: string|null,
     *     authorization_language: string|null,
     *     concern_terms: list<array<string, mixed>>,
     *     shop_terms_fingerprint: string
     * }
     */
    public function snapshotFor(
        FinancialDocumentType $documentType,
        ShopSettings $settings,
        ?string $customerType,
        ?RepairOrder $repairOrder = null,
    ): array {
        $resolved = $repairOrder !== null
            ? $this->termsResolver->forRepairOrder($repairOrder, $documentType, $settings)
            : [
                'global_disclaimer' => $settings->globalDocumentDisclaimerFor($documentType),
                'authorization_language' => $this->authorizationLanguageForSnapshot($settings),
                'shop_terms_fingerprint' => '',
                'concern_terms' => [],
            ];

        // Legacy single customer-type disclaimer kept for older PDF presenters / readers.
        // Deduplicate against concern terms: class rows often fall back to the same map entry.
        $customerTypeDisclaimer = $settings->customerTypeDocumentDisclaimerFor($customerType);
        if ($repairOrder !== null && $resolved['concern_terms'] !== []) {
            $modes = collect($resolved['concern_terms'])
                ->pluck('terms_mode')
                ->unique()
                ->values();
            $hasReplace = $modes->contains(BillingClassDocumentTermsMode::Replace->value);
            $concernTexts = collect($resolved['concern_terms'])
                ->pluck('terms')
                ->filter()
                ->map(fn (mixed $text): string => trim((string) $text))
                ->filter()
                ->unique()
                ->values();

            if ($hasReplace) {
                $customerTypeDisclaimer = $concernTexts->isNotEmpty()
                    ? $concernTexts->implode("\n\n")
                    : null;
            } elseif ($concernTexts->isNotEmpty()) {
                $parts = collect([
                    filled($customerTypeDisclaimer) ? trim((string) $customerTypeDisclaimer) : null,
                ])
                    ->merge($concernTexts)
                    ->filter()
                    ->unique()
                    ->values();
                $customerTypeDisclaimer = $parts->isNotEmpty() ? $parts->implode("\n\n") : null;
            }
        }

        return [
            'global_disclaimer' => $resolved['global_disclaimer'],
            'customer_type' => filled($customerType) ? trim((string) $customerType) : null,
            'customer_type_disclaimer' => filled($customerTypeDisclaimer) ? trim((string) $customerTypeDisclaimer) : null,
            'authorization_language' => $resolved['authorization_language']
                ?? $this->authorizationLanguageForSnapshot($settings),
            'concern_terms' => $resolved['concern_terms'],
            'shop_terms_fingerprint' => $resolved['shop_terms_fingerprint'] ?? '',
        ];
    }

    private function authorizationLanguageForSnapshot(ShopSettings $settings): ?string
    {
        $language = $settings->authorizationLanguage();

        if (! filled($language)) {
            return null;
        }

        $shopName = filled($settings->shop_name) ? trim((string) $settings->shop_name) : config('app.name');

        return str_replace('{shop_name}', $shopName, $language);
    }

    /**
     * Resolve disclaimer sections from a stored snapshot. Snapshot `documents` is authoritative.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     global_disclaimer: string,
     *     customer_type: string|null,
     *     customer_type_disclaimer: string|null,
     *     authorization_language: string|null,
     *     concern_terms: list<array<string, mixed>>
     * }
     */
    public function resolveFromSnapshot(array $snapshot): array
    {
        $documents = data_get($snapshot, 'documents');

        if (is_array($documents) && filled($documents['global_disclaimer'] ?? null)) {
            return [
                'global_disclaimer' => trim((string) $documents['global_disclaimer']),
                'customer_type' => filled($documents['customer_type'] ?? null)
                    ? trim((string) $documents['customer_type'])
                    : data_get($snapshot, 'customer.customer_type'),
                'customer_type_disclaimer' => filled($documents['customer_type_disclaimer'] ?? null)
                    ? trim((string) $documents['customer_type_disclaimer'])
                    : null,
                'authorization_language' => filled($documents['authorization_language'] ?? null)
                    ? trim((string) $documents['authorization_language'])
                    : null,
                'concern_terms' => is_array($documents['concern_terms'] ?? null)
                    ? $documents['concern_terms']
                    : [],
            ];
        }

        $documentType = FinancialDocumentType::tryFrom((string) data_get($snapshot, 'document_type', 'estimate'))
            ?? FinancialDocumentType::Estimate;

        $settingsBlock = is_array(data_get($snapshot, 'settings')) ? $snapshot['settings'] : [];
        $customerType = data_get($snapshot, 'customer.customer_type');

        $globalDisclaimer = $documentType === FinancialDocumentType::Invoice
            ? (string) ($settingsBlock['invoice_disclaimer'] ?? $settingsBlock['estimate_disclaimer'] ?? '')
            : (string) ($settingsBlock['estimate_disclaimer'] ?? '');

        return [
            'global_disclaimer' => trim($globalDisclaimer),
            'customer_type' => filled($customerType) ? trim((string) $customerType) : null,
            'customer_type_disclaimer' => null,
            'authorization_language' => filled($settingsBlock['authorization_language'] ?? null)
                ? trim((string) $settingsBlock['authorization_language'])
                : null,
            'concern_terms' => [],
        ];
    }
}
