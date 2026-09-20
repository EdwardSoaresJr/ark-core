<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * Presentation-only projection for customer-facing part lines.
 *
 * Resolves customer labels through shop presentation policy. Never mutates
 * procurement identity fields on the line payload.
 */
final class CustomerPartPresentationPresenter
{
    public function __construct(
        private readonly CustomerPartDescriptionPresenter $descriptionPresenter,
    ) {}

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     * @return array<string, mixed>
     */
    public function presentLine(
        CustomerPartPresentationPolicy $policy,
        array $line,
        array $siblingPartDescriptions = [],
    ): array {
        if (($line['type'] ?? '') !== RepairOrderLineType::Part->value) {
            return $line;
        }

        $line['customer_part_description'] = $this->resolveDescription($policy, $line, $siblingPartDescriptions);

        if ($policy->showManufacturerNumber) {
            $partNumber = $this->presentationPartNumber($line);

            if ($partNumber !== null) {
                $line['customer_part_number'] = $partNumber;
            }
        }

        if ($policy->showSupplier) {
            $vendor = trim((string) ($line['vendor_name'] ?? ''));

            if ($vendor !== '') {
                $line['customer_part_vendor'] = $vendor;
            }
        }

        if ($policy->showSupplierSku) {
            $sku = $this->presentationSupplierSku($line);

            if ($sku !== null) {
                $line['customer_part_supplier_sku'] = $sku;
            }
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     */
    private function resolveDescription(
        CustomerPartPresentationPolicy $policy,
        array $line,
        array $siblingPartDescriptions,
    ): string {
        $explicit = trim((string) ($line['customer_description'] ?? ''));
        $inventory = trim((string) ($line['description'] ?? ''));
        $source = CustomerDescriptionSource::tryFromStored($line['customer_description_source'] ?? null);

        $manual = $source?->isManual() ? $explicit : '';

        if ($manual !== '') {
            return $manual;
        }

        if ($policy->labelsLocked) {
            return $explicit !== '' ? $explicit : $inventory;
        }

        return match ($policy->descriptionMode) {
            CustomerPartDescriptionMode::Verbatim => $inventory,
            CustomerPartDescriptionMode::Manual => $explicit !== ''
                ? $explicit
                : ($inventory !== '' ? $inventory : ''),
            CustomerPartDescriptionMode::CleanedWithBrand => $this->withBrand(
                $this->cleanedLabel($line, $siblingPartDescriptions),
                $inventory,
            ),
            CustomerPartDescriptionMode::Cleaned => $this->cleanedLabel($line, $siblingPartDescriptions),
        };
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     */
    private function cleanedLabel(array $line, array $siblingPartDescriptions): string
    {
        return $this->descriptionPresenter->presentFromSnapshotLine($line, $siblingPartDescriptions);
    }

    private function withBrand(string $cleaned, string $inventory): string
    {
        $cleaned = trim($cleaned);
        $brand = $this->inferBrand($inventory);

        if ($brand === '' || $cleaned === '') {
            return $cleaned !== '' ? $cleaned : $inventory;
        }

        if (str_starts_with(mb_strtolower($cleaned), mb_strtolower($brand).' ')) {
            return $cleaned;
        }

        return $brand.' '.$cleaned;
    }

    private function inferBrand(string $inventory): string
    {
        $inventory = trim($inventory);

        if ($inventory === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9\/&+.\-]*)\b/u', $inventory, $matches) !== 1) {
            return '';
        }

        $token = $matches[1];
        $normalized = mb_strtolower($token);

        $known = [
            'champion', 'gates', 'dorman', 'moog', 'motorcraft', 'mopar', 'acdelco', 'ngk', 'bosch',
            'bilstein', 'brembo', 'denso', 'prestone', 'wagner', 'raybestos', 'monroe', 'timken',
            'continental', 'carquest', 'duralast', 'fel-pro', 'felpro', 'stant', 'oem',
        ];

        if (! in_array($normalized, $known, true)) {
            return '';
        }

        return match ($normalized) {
            'acdelco' => 'ACDelco',
            'fel-pro', 'felpro' => 'Fel-Pro',
            'oem' => 'OEM',
            'ngk' => 'NGK',
            default => mb_convert_case($token, MB_CASE_TITLE, 'UTF-8'),
        };
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function presentationPartNumber(array $line): ?string
    {
        $partNumber = trim((string) ($line['part_number'] ?? ''));

        if ($partNumber === '') {
            return null;
        }

        $description = trim((string) ($line['description'] ?? ''));
        $pattern = '/^([A-Za-z][A-Za-z0-9\/&+\-.]*)\s+'.preg_quote($partNumber, '/').'\b/u';

        if ($description !== '' && preg_match($pattern, $description, $matches) === 1) {
            return trim($matches[1].' '.$partNumber);
        }

        return $partNumber;
    }

    /**
     * Supplier SKU is not a dedicated line column today — only expose part_number
     * when the shop explicitly asks for supplier SKU (never invent a second identity).
     *
     * @param  array<string, mixed>  $line
     */
    private function presentationSupplierSku(array $line): ?string
    {
        $partNumber = trim((string) ($line['part_number'] ?? ''));

        return $partNumber !== '' ? $partNumber : null;
    }
}
