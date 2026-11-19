<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Parts\CustomerDocumentPresentation;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable billing-class commercial rules stamped on a repair order at creation.
 *
 * Schema 1: customer_types commercial slice (discounts, fee overrides, matrix keys, presentation).
 * Schema 2: also freezes parts_matrices definitions so tier/markup edits cannot reprice old ROs.
 * Schema 2+ also carries warranty / document-terms fields on each customer_types row plus shop-level
 * warranty and document disclaimers when stamped from current settings. Older stamps omit those keys;
 * readers must not invent them from live ShopSettings for an already-stamped RO.
 *
 * null on the RO means legacy live-settings behavior until stamped (living only).
 * Terminal ROs must not invent rules from live settings — see RepairOrderPricingContext.
 */
final class CommercialPricingProfile
{
    public const CURRENT_SCHEMA = 2;

    /**
     * @param  list<array<string, mixed>>  $customerTypes
     * @param  list<array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}>  $partsMatrices
     * @param  array<string, mixed>|null  $shopWarranty
     * @param  array<string, string>  $customerTypeDisclaimers
     */
    public function __construct(
        public readonly array $customerTypes,
        public readonly int $version = 1,
        public readonly int $schema = self::CURRENT_SCHEMA,
        public readonly array $partsMatrices = [],
        public readonly ?string $defaultPartsMatrixKey = null,
        public readonly ?array $shopWarranty = null,
        public readonly ?string $estimateDisclaimer = null,
        public readonly ?string $invoiceDisclaimer = null,
        public readonly ?string $authorizationLanguage = null,
        public readonly array $customerTypeDisclaimers = [],
        public readonly bool $hasDocumentTermsStamp = false,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $rows = collect($settings->customerTypeRows())
            ->map(fn (array $row): array => self::commercialSlice($row))
            ->values()
            ->all();

        $matrices = collect($settings->partsMatrices())
            ->map(fn (array $matrix): array => self::matrixSlice($matrix))
            ->values()
            ->all();

        $disclaimers = collect($settings->customerTypeDisclaimerMap())
            ->map(fn ($text): string => trim((string) $text))
            ->filter()
            ->all();

        return new self(
            customerTypes: $rows,
            version: max(1, (int) ($settings->commercial_pricing_profile_version ?? 1)),
            schema: self::CURRENT_SCHEMA,
            partsMatrices: $matrices,
            defaultPartsMatrixKey: $settings->defaultPartsMatrix()['key'] ?? null,
            shopWarranty: $settings->shopWarranty()->toArray(),
            estimateDisclaimer: filled($settings->estimate_disclaimer)
                ? trim((string) $settings->estimate_disclaimer)
                : null,
            invoiceDisclaimer: filled($settings->invoice_disclaimer)
                ? trim((string) $settings->invoice_disclaimer)
                : null,
            authorizationLanguage: filled($settings->authorizationLanguage())
                ? trim((string) $settings->authorizationLanguage())
                : null,
            customerTypeDisclaimers: $disclaimers,
            hasDocumentTermsStamp: true,
        );
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function tryFromStored(?array $stored): ?self
    {
        if ($stored === null || $stored === []) {
            return null;
        }

        $rows = collect($stored['customer_types'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['name'] ?? null))
            ->map(fn (array $row): array => self::commercialSlice($row))
            ->values()
            ->all();

        if ($rows === []) {
            return null;
        }

        $matrices = collect($stored['parts_matrices'] ?? [])
            ->filter(fn ($matrix): bool => is_array($matrix) && filled($matrix['key'] ?? null))
            ->map(fn (array $matrix): array => self::matrixSlice($matrix))
            ->values()
            ->all();

        $hasDocumentTermsStamp = array_key_exists('shop_warranty', $stored)
            || array_key_exists('estimate_disclaimer', $stored)
            || array_key_exists('document_terms_stamp', $stored)
            || collect($rows)->contains(
                fn (array $row): bool => array_key_exists('warranty_mode', $row)
                    || array_key_exists('document_terms_mode', $row),
            );

        $disclaimers = collect($stored['customer_type_disclaimers'] ?? [])
            ->map(fn ($text): string => trim((string) $text))
            ->filter()
            ->all();

        return new self(
            customerTypes: $rows,
            version: (int) ($stored['version'] ?? 1),
            schema: (int) ($stored['schema'] ?? 1),
            partsMatrices: $matrices,
            defaultPartsMatrixKey: filled($stored['default_parts_matrix_key'] ?? null)
                ? (string) $stored['default_parts_matrix_key']
                : null,
            shopWarranty: is_array($stored['shop_warranty'] ?? null) ? $stored['shop_warranty'] : null,
            estimateDisclaimer: filled($stored['estimate_disclaimer'] ?? null)
                ? trim((string) $stored['estimate_disclaimer'])
                : null,
            invoiceDisclaimer: filled($stored['invoice_disclaimer'] ?? null)
                ? trim((string) $stored['invoice_disclaimer'])
                : null,
            authorizationLanguage: filled($stored['authorization_language'] ?? null)
                ? trim((string) $stored['authorization_language'])
                : null,
            customerTypeDisclaimers: $disclaimers,
            hasDocumentTermsStamp: $hasDocumentTermsStamp,
        );
    }

    /**
     * Merge current shop parts matrices into an existing stamp that only has keys (schema 1).
     * Does not refresh warranty or document terms from live settings.
     */
    public function withPartsMatricesFrom(ShopSettings $settings): self
    {
        if ($this->partsMatrices !== []) {
            return $this;
        }

        $matrices = collect($settings->partsMatrices())
            ->map(fn (array $matrix): array => self::matrixSlice($matrix))
            ->values()
            ->all();

        return new self(
            customerTypes: $this->customerTypes,
            version: $this->version,
            schema: max($this->schema, self::CURRENT_SCHEMA),
            partsMatrices: $matrices,
            defaultPartsMatrixKey: $this->defaultPartsMatrixKey
                ?? ($settings->defaultPartsMatrix()['key'] ?? null),
            shopWarranty: $this->shopWarranty,
            estimateDisclaimer: $this->estimateDisclaimer,
            invoiceDisclaimer: $this->invoiceDisclaimer,
            authorizationLanguage: $this->authorizationLanguage,
            customerTypeDisclaimers: $this->customerTypeDisclaimers,
            hasDocumentTermsStamp: $this->hasDocumentTermsStamp,
        );
    }

    /**
     * @return array{type: string, percent: string}|null
     */
    public function standingDiscountPolicy(?string $customerType): ?array
    {
        return ShopSettings::standingDiscountPolicyFromRow($this->customerTypeFor($customerType));
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForCustomerType(?string $customerType): array
    {
        $row = $this->customerTypeFor($customerType);

        if (! ($row['shop_fees_enabled'] ?? false)) {
            return ['enabled' => false, 'rate' => null];
        }

        return [
            'enabled' => true,
            'rate' => filled($row['shop_fee_rate_override'] ?? null)
                ? (string) $row['shop_fee_rate_override']
                : null,
        ];
    }

    public function documentPresentationFor(?string $customerType): CustomerDocumentPresentation
    {
        return CustomerDocumentPresentation::fromStoredRow($this->customerTypeFor($customerType));
    }

    public function documentPresentationProfileFor(?string $customerType): CustomerPartPresentationProfile
    {
        $presentation = $this->documentPresentationFor($customerType);

        return match (true) {
            $presentation->showPartNumber && $presentation->showVendor => CustomerPartPresentationProfile::Warranty,
            $presentation->showPartNumber && ! $presentation->showVendor => CustomerPartPresentationProfile::RepairPal,
            default => CustomerPartPresentationProfile::Retail,
        };
    }

    public function defaultPartsMatrixKeyFor(?string $customerType): ?string
    {
        $key = $this->customerTypeFor($customerType)['default_parts_matrix_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    /**
     * Resolve a matrix by key. Unknown keys fall back to the stamped default matrix
     * (advisor convenience for omitted keys). Use {@see partsMatrixExact()} when an
     * explicit key must either match or fail.
     *
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}|null
     */
    public function partsMatrixByKey(?string $key): ?array
    {
        if ($this->partsMatrices === []) {
            return null;
        }

        if (! filled($key)) {
            $key = $this->defaultPartsMatrixKey;
        }

        if (filled($key)) {
            $match = $this->partsMatrixExact($key);
            if ($match !== null) {
                return $match;
            }
        }

        return collect($this->partsMatrices)->firstWhere('is_default', true)
            ?? $this->partsMatrices[0]
            ?? null;
    }

    /**
     * Exact key match only — no default fallback. Returns null for unknown keys so
     * advisors cannot silently price against a matrix created after this RO opened.
     *
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}|null
     */
    public function partsMatrixExact(?string $key): ?array
    {
        if ($this->partsMatrices === [] || ! filled($key)) {
            return null;
        }

        $match = collect($this->partsMatrices)->firstWhere('key', $key);

        return is_array($match) ? $match : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerTypeRows(): array
    {
        return $this->customerTypes;
    }

    public function shopWarranty(): BillingClassWarranty
    {
        return BillingClassWarranty::fromArray($this->shopWarranty);
    }

    public function globalDocumentDisclaimerFor(string $documentType): string
    {
        $text = $documentType === 'invoice'
            ? ($this->invoiceDisclaimer ?? $this->estimateDisclaimer)
            : $this->estimateDisclaimer;

        return filled($text) ? trim((string) $text) : '';
    }

    public function customerTypeDocumentDisclaimerFor(?string $customerType): ?string
    {
        if (! filled($customerType)) {
            return null;
        }

        $key = mb_strtolower(trim($customerType));
        $map = collect($this->customerTypeDisclaimers)
            ->mapWithKeys(fn ($text, $mapKey): array => [mb_strtolower((string) $mapKey) => trim((string) $text)]);

        $direct = $map->get($key);
        if (filled($direct)) {
            return $direct;
        }

        $row = $this->customerTypeRowByKeyOrName($key, $customerType);

        return filled($row['document_terms'] ?? null) ? trim((string) $row['document_terms']) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerTypeRowByKeyOrName(?string $key, ?string $name = null): ?array
    {
        $normalizedKey = filled($key) ? mb_strtolower(trim($key)) : null;
        $normalizedName = filled($name) ? mb_strtolower(trim($name)) : null;

        return collect($this->customerTypes)
            ->first(function (array $row) use ($normalizedKey, $normalizedName): bool {
                $rowKey = filled($row['key'] ?? null) ? mb_strtolower((string) $row['key']) : null;
                $rowName = filled($row['name'] ?? null) ? mb_strtolower((string) $row['name']) : null;
                $laborKey = filled($row['labor_column_key'] ?? null)
                    ? mb_strtolower((string) $row['labor_column_key'])
                    : null;

                return ($normalizedKey !== null && ($rowKey === $normalizedKey || $laborKey === $normalizedKey || $rowName === $normalizedKey))
                    || ($normalizedName !== null && $rowName === $normalizedName);
            });
    }

    /**
     * @return array{
     *     schema: int,
     *     version: int,
     *     customer_types: list<array<string, mixed>>,
     *     parts_matrices: list<array<string, mixed>>,
     *     default_parts_matrix_key: string|null,
     *     shop_warranty: array<string, mixed>|null,
     *     estimate_disclaimer: string|null,
     *     invoice_disclaimer: string|null,
     *     authorization_language: string|null,
     *     customer_type_disclaimers: array<string, string>,
     *     document_terms_stamp: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'version' => $this->version,
            'customer_types' => $this->customerTypes,
            'parts_matrices' => $this->partsMatrices,
            'default_parts_matrix_key' => $this->defaultPartsMatrixKey,
            'shop_warranty' => $this->shopWarranty,
            'estimate_disclaimer' => $this->estimateDisclaimer,
            'invoice_disclaimer' => $this->invoiceDisclaimer,
            'authorization_language' => $this->authorizationLanguage,
            'customer_type_disclaimers' => $this->customerTypeDisclaimers,
            'document_terms_stamp' => $this->hasDocumentTermsStamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function commercialSlice(array $row): array
    {
        $name = trim((string) ($row['name'] ?? 'Retail')) ?: 'Retail';
        $shopFeesEnabled = (bool) ($row['shop_fees_enabled'] ?? true);
        $rateOverride = $shopFeesEnabled && filled($row['shop_fee_rate_override'] ?? null)
            ? number_format((float) $row['shop_fee_rate_override'], 3, '.', '')
            : null;
        $discountType = (string) ($row['discount_type'] ?? 'none');
        if (! in_array($discountType, ['none', 'labor', 'parts', 'both'], true)) {
            $discountType = 'none';
        }

        $presentation = CustomerDocumentPresentation::fromStoredRow([
            ...$row,
            'name' => $name,
        ]);

        $slice = [
            'name' => $name,
            'shop_fees_enabled' => $shopFeesEnabled,
            'shop_fee_rate_override' => $rateOverride,
            'fee_override' => $shopFeesEnabled ? $rateOverride : 'none',
            'discount_type' => $discountType,
            'discount_amount' => $discountType === 'none'
                ? null
                : (filled($row['discount_amount'] ?? null)
                    ? number_format((float) $row['discount_amount'], 2, '.', '')
                    : null),
            'default_parts_matrix_key' => filled($row['default_parts_matrix_key'] ?? null)
                ? (string) $row['default_parts_matrix_key']
                : null,
            ...$presentation->toRow(),
            // Legacy label kept for older stamps/readers; flags are authoritative.
            'document_presentation_profile' => match (true) {
                $presentation->showPartNumber && $presentation->showVendor => CustomerPartPresentationProfile::Warranty->value,
                $presentation->showPartNumber && ! $presentation->showVendor => CustomerPartPresentationProfile::RepairPal->value,
                default => CustomerPartPresentationProfile::Retail->value,
            },
            'concern_billing_posture' => filled($row['concern_billing_posture'] ?? null)
                ? (string) $row['concern_billing_posture']
                : null,
            'pill_color' => $row['pill_color'] ?? null,
        ];

        if (filled($row['key'] ?? null)) {
            $slice['key'] = (string) $row['key'];
        }
        if (filled($row['labor_column_key'] ?? null)) {
            $slice['labor_column_key'] = (string) $row['labor_column_key'];
        }

        // Preserve absence on older stamps: only write warranty/terms keys when present upstream.
        if (array_key_exists('warranty_mode', $row) || array_key_exists('warranty', $row)) {
            $slice['warranty_mode'] = BillingClassWarrantyMode::tryFromMixed($row['warranty_mode'] ?? null)->value;
            $slice['warranty'] = BillingClassWarranty::fromArray(
                is_array($row['warranty'] ?? null) ? $row['warranty'] : null,
            )->toArray();
        }
        if (array_key_exists('document_terms_mode', $row) || array_key_exists('document_terms', $row)) {
            $slice['document_terms_mode'] = BillingClassDocumentTermsMode::tryFromMixed(
                $row['document_terms_mode'] ?? null,
            )->value;
            $slice['document_terms'] = filled($row['document_terms'] ?? null)
                ? trim((string) $row['document_terms'])
                : null;
        }

        return $slice;
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}
     */
    private static function matrixSlice(array $matrix): array
    {
        $rows = collect($matrix['rows'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'min_cost' => (string) ($row['min_cost'] ?? '0.00'),
                    'max_cost' => array_key_exists('max_cost', $row) && $row['max_cost'] !== null
                        ? (string) $row['max_cost']
                        : null,
                    'markup_percentage' => isset($row['markup_percentage'])
                        ? (string) $row['markup_percentage']
                        : null,
                    'margin_percentage' => isset($row['margin_percentage'])
                        ? (string) $row['margin_percentage']
                        : null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => (string) $matrix['key'],
            'name' => (string) ($matrix['name'] ?? $matrix['key']),
            'is_default' => (bool) ($matrix['is_default'] ?? false),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerTypeFor(?string $customerType): array
    {
        $normalized = mb_strtolower(trim((string) $customerType));

        return collect($this->customerTypes)
            ->first(fn (array $row): bool => mb_strtolower((string) $row['name']) === $normalized)
            ?? $this->customerTypes[0]
            ?? [
                'name' => 'Retail',
                'shop_fees_enabled' => true,
                'shop_fee_rate_override' => null,
                'discount_type' => 'none',
                'discount_amount' => null,
                'default_parts_matrix_key' => null,
                ...((new CustomerDocumentPresentation)->toRow()),
                'document_presentation_profile' => CustomerPartPresentationProfile::Retail->value,
            ];
    }
}
