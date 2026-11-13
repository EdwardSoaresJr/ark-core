<?php

namespace App\Ark\Operations\Parts;

/**
 * Per billing-class customer document part presentation.
 *
 * Part description is always shown. Part number, brand, and vendor/supplier
 * are independent checkboxes stored on the billing class — never hard-coded
 * by Warranty/RepairPal/Fleet profile enums.
 */
final readonly class CustomerDocumentPresentation
{
    public function __construct(
        public bool $showPartNumber = false,
        public bool $showPartBrand = false,
        public bool $showVendor = false,
    ) {}

    /**
     * Migrate a legacy predefined profile into equivalent checkbox flags.
     */
    public static function fromLegacyProfile(CustomerPartPresentationProfile $profile): self
    {
        return new self(
            showPartNumber: $profile->showsPartNumber(),
            showPartBrand: $profile->showsBrand(),
            showVendor: $profile->showsVendor(),
        );
    }

    public static function defaultForBillingClassName(?string $billingClassName): self
    {
        return self::fromLegacyProfile(
            CustomerPartPresentationProfile::defaultForBillingClassName($billingClassName),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromStoredRow(array $row): self
    {
        $hasExplicitFlags = array_key_exists('document_show_part_number', $row)
            || array_key_exists('document_show_part_brand', $row)
            || array_key_exists('document_show_vendor', $row);

        if ($hasExplicitFlags) {
            return new self(
                showPartNumber: self::toBool($row['document_show_part_number'] ?? false),
                showPartBrand: self::toBool($row['document_show_part_brand'] ?? false),
                showVendor: self::toBool($row['document_show_vendor'] ?? false),
            );
        }

        if (filled($row['document_presentation_profile'] ?? null)) {
            return self::fromLegacyProfile(
                CustomerPartPresentationProfile::fromStored((string) $row['document_presentation_profile']),
            );
        }

        return self::defaultForBillingClassName(
            is_string($row['name'] ?? null) ? $row['name'] : null,
        );
    }

    /**
     * Frozen snapshot payload (issued docs / customer slice).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromFrozen(?array $payload, ?string $legacyProfile = null): ?self
    {
        if (is_array($payload) && (
            array_key_exists('show_part_number', $payload)
            || array_key_exists('show_part_brand', $payload)
            || array_key_exists('show_vendor', $payload)
            || array_key_exists('document_show_part_number', $payload)
        )) {
            return new self(
                showPartNumber: self::toBool(
                    $payload['show_part_number']
                        ?? $payload['document_show_part_number']
                        ?? false,
                ),
                showPartBrand: self::toBool(
                    $payload['show_part_brand']
                        ?? $payload['document_show_part_brand']
                        ?? false,
                ),
                showVendor: self::toBool(
                    $payload['show_vendor']
                        ?? $payload['document_show_vendor']
                        ?? false,
                ),
            );
        }

        if (filled($legacyProfile)) {
            return self::fromLegacyProfile(
                CustomerPartPresentationProfile::fromStored($legacyProfile),
            );
        }

        return null;
    }

    /**
     * @return array{
     *     document_show_part_number: bool,
     *     document_show_part_brand: bool,
     *     document_show_vendor: bool
     * }
     */
    public function toRow(): array
    {
        return [
            'document_show_part_number' => $this->showPartNumber,
            'document_show_part_brand' => $this->showPartBrand,
            'document_show_vendor' => $this->showVendor,
        ];
    }

    /**
     * @return array{
     *     show_part_number: bool,
     *     show_part_brand: bool,
     *     show_vendor: bool
     * }
     */
    public function toFrozen(): array
    {
        return [
            'show_part_number' => $this->showPartNumber,
            'show_part_brand' => $this->showPartBrand,
            'show_vendor' => $this->showVendor,
        ];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
