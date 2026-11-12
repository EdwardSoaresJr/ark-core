<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Immutable tax rules stamped on a repair order.
 *
 * Schema 1: combined rate + taxability flags (Slice A / historical).
 * Schema 2: freezes selected catalog profile identity, components, associations,
 * document presentation, and sourcing metadata. Calculator in Slice B still uses
 * the combined rate + taxability flags — component-level bases are Slice C.
 *
 * null on the RO means legacy live-settings behavior until stamped (living only).
 * Terminal ROs must not invent tax rules from live settings.
 */
final class TaxProfile
{
    public const CURRENT_SCHEMA = 2;

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, mixed>|null  $associations
     * @param  array<string, mixed>|null  $sourcing
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $label,
        public readonly string $rate,
        public readonly bool $taxableLabor,
        public readonly bool $taxableParts,
        public readonly bool $taxableShopFees,
        public readonly int $schema = self::CURRENT_SCHEMA,
        public readonly ?string $profileKey = null,
        public readonly ?string $profileName = null,
        public readonly string $documentPresentation = ShopTaxCatalog::PRESENTATION_COMBINED,
        public readonly array $components = [],
        public readonly ?array $associations = null,
        public readonly ?array $sourcing = null,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $profiles = ShopTaxCatalog::profilesFromShopSettings($settings);
        $default = collect($profiles)->first(
            fn (array $profile): bool => (bool) ($profile['is_default'] ?? false) && (bool) ($profile['is_active'] ?? true),
        ) ?? collect($profiles)->first(
            fn (array $profile): bool => (bool) ($profile['is_active'] ?? true),
        );

        if (! is_array($default)) {
            return new self(
                enabled: (bool) $settings->tax_enabled,
                label: filled($settings->tax_label) ? (string) $settings->tax_label : 'Tax',
                rate: filled($settings->default_tax_rate) ? (string) $settings->default_tax_rate : '0',
                taxableLabor: (bool) $settings->taxable_labor,
                taxableParts: (bool) $settings->taxable_parts,
                taxableShopFees: (bool) $settings->taxable_shop_fees,
                schema: self::CURRENT_SCHEMA,
                documentPresentation: ShopTaxCatalog::documentPresentation($settings),
                sourcing: [
                    'location_kind' => TaxSourcingLocationKind::Shop->value,
                    'selection_status' => 'default',
                    'matcher_note' => 'Shop default (no catalog profile).',
                ],
            );
        }

        return self::fromCatalogProfile(
            $default,
            enabled: (bool) $settings->tax_enabled,
            documentPresentation: ShopTaxCatalog::documentPresentation($settings),
            sourcing: [
                'location_kind' => TaxSourcingLocationKind::Shop->value,
                'selection_status' => 'default',
                'matcher_note' => 'Stamped from shop default tax profile.',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>|null  $sourcing
     */
    public static function fromCatalogProfile(
        array $profile,
        bool $enabled,
        string $documentPresentation = ShopTaxCatalog::PRESENTATION_COMBINED,
        ?array $sourcing = null,
    ): self {
        $name = filled($profile['name'] ?? null) ? (string) $profile['name'] : 'Tax';
        $components = is_array($profile['components'] ?? null) ? array_values($profile['components']) : [];

        return new self(
            enabled: $enabled,
            label: $name,
            rate: ShopTaxCatalog::combinedRate($profile),
            taxableLabor: (bool) ($profile['taxable_labor'] ?? false),
            taxableParts: (bool) ($profile['taxable_parts'] ?? true),
            taxableShopFees: (bool) ($profile['taxable_shop_fees'] ?? false),
            schema: self::CURRENT_SCHEMA,
            profileKey: filled($profile['key'] ?? null) ? (string) $profile['key'] : null,
            profileName: $name,
            documentPresentation: in_array($documentPresentation, [
                ShopTaxCatalog::PRESENTATION_COMBINED,
                ShopTaxCatalog::PRESENTATION_ITEMIZED,
            ], true) ? $documentPresentation : ShopTaxCatalog::PRESENTATION_COMBINED,
            components: $components,
            associations: [
                'state' => $profile['state'] ?? null,
                'counties' => array_values(is_array($profile['counties'] ?? null) ? $profile['counties'] : []),
                'cities' => array_values(is_array($profile['cities'] ?? null) ? $profile['cities'] : []),
                'zip_codes' => array_values(is_array($profile['zip_codes'] ?? null) ? $profile['zip_codes'] : []),
            ],
            sourcing: $sourcing,
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

        $schema = (int) ($stored['schema'] ?? 1);

        return new self(
            enabled: (bool) ($stored['enabled'] ?? false),
            label: filled($stored['label'] ?? null) ? (string) $stored['label'] : 'Tax',
            rate: (string) ($stored['rate'] ?? '0'),
            taxableLabor: (bool) ($stored['taxable_labor'] ?? false),
            taxableParts: (bool) ($stored['taxable_parts'] ?? true),
            taxableShopFees: (bool) ($stored['taxable_shop_fees'] ?? false),
            schema: $schema,
            profileKey: filled($stored['profile_key'] ?? null) ? (string) $stored['profile_key'] : null,
            profileName: filled($stored['profile_name'] ?? null) ? (string) $stored['profile_name'] : null,
            documentPresentation: (string) ($stored['document_presentation'] ?? ShopTaxCatalog::PRESENTATION_COMBINED),
            components: is_array($stored['components'] ?? null) ? array_values($stored['components']) : [],
            associations: is_array($stored['associations'] ?? null) ? $stored['associations'] : null,
            sourcing: is_array($stored['sourcing'] ?? null) ? $stored['sourcing'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'enabled' => $this->enabled,
            'label' => $this->label,
            'rate' => $this->rate,
            'taxable_labor' => $this->taxableLabor,
            'taxable_parts' => $this->taxableParts,
            'taxable_shop_fees' => $this->taxableShopFees,
            'schema' => $this->schema,
        ];

        if ($this->schema >= 2) {
            $payload['profile_key'] = $this->profileKey;
            $payload['profile_name'] = $this->profileName ?? $this->label;
            $payload['document_presentation'] = $this->documentPresentation;
            $payload['components'] = $this->components;
            $payload['associations'] = $this->associations;
            $payload['sourcing'] = $this->sourcing;
        }

        return $payload;
    }

    public function withSourcing(?array $sourcing): self
    {
        return new self(
            enabled: $this->enabled,
            label: $this->label,
            rate: $this->rate,
            taxableLabor: $this->taxableLabor,
            taxableParts: $this->taxableParts,
            taxableShopFees: $this->taxableShopFees,
            schema: max($this->schema, 2),
            profileKey: $this->profileKey,
            profileName: $this->profileName,
            documentPresentation: $this->documentPresentation,
            components: $this->components,
            associations: $this->associations,
            sourcing: $sourcing,
        );
    }
}
