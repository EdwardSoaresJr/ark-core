<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Settings\ShopSettings;

final class DocumentDisclaimerComposer
{
    /**
     * @return array{
     *     global_disclaimer: string,
     *     customer_type: string|null,
     *     customer_type_disclaimer: string|null,
     *     authorization_language: string|null
     * }
     */
    public function snapshotFor(FinancialDocumentType $documentType, ShopSettings $settings, ?string $customerType): array
    {
        return [
            'global_disclaimer' => $settings->globalDocumentDisclaimerFor($documentType),
            'customer_type' => filled($customerType) ? trim((string) $customerType) : null,
            'customer_type_disclaimer' => $settings->customerTypeDocumentDisclaimerFor($customerType),
            'authorization_language' => $this->authorizationLanguageForSnapshot($settings),
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
     *     authorization_language: string|null
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
        ];
    }
}
