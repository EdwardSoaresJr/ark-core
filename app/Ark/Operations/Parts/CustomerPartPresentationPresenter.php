<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;

/**
 * Presentation-only projection for customer-facing part lines.
 *
 * Part descriptions render verbatim. Optional profile rules may still surface
 * audit part number and vendor metadata without rewriting the description.
 */
final class CustomerPartPresentationPresenter
{
    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     * @return array<string, mixed>
     */
    public function presentLine(
        CustomerPartPresentationProfile $profile,
        array $line,
        array $siblingPartDescriptions = [],
    ): array {
        unset($siblingPartDescriptions);

        if (($line['type'] ?? '') !== RepairOrderLineType::Part->value) {
            return $line;
        }

        $explicitCustomerDescription = trim((string) ($line['customer_description'] ?? ''));

        $line['customer_part_description'] = $explicitCustomerDescription !== ''
            ? $explicitCustomerDescription
            : trim((string) ($line['description'] ?? ''));

        if ($profile->showsPartNumber()) {
            $partNumber = $this->presentationPartNumber($line);

            if ($partNumber !== null) {
                $line['customer_part_number'] = $partNumber;
            }
        }

        if ($profile->showsVendor()) {
            $vendor = trim((string) ($line['vendor_name'] ?? ''));

            if ($vendor !== '') {
                $line['customer_part_vendor'] = $vendor;
            }
        }

        return $line;
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
}
