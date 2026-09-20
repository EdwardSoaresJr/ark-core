<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrderLine;

/**
 * Resolves snapshotted customer-facing part description fields for create/update.
 */
final class CustomerPartDescriptionAttributes
{
    public function __construct(
        private readonly CustomerPartDescriptionPresenter $presenter,
    ) {}

    /**
     * @return array{customer_description: ?string, customer_description_source: ?string}
     */
    public function forCreate(string $inventoryDescription, ?string $explicitCustomerDescription = null, ?string $brand = null): array
    {
        $explicit = trim((string) $explicitCustomerDescription);
        $generated = trim($this->presenter->generate($inventoryDescription, $brand));

        if ($explicit !== '' && $explicit !== $generated) {
            return [
                'customer_description' => mb_substr($explicit, 0, 255),
                'customer_description_source' => CustomerDescriptionSource::Manual->value,
            ];
        }

        if ($generated === '') {
            return [
                'customer_description' => null,
                'customer_description_source' => null,
            ];
        }

        return [
            'customer_description' => mb_substr($generated, 0, 255),
            'customer_description_source' => CustomerDescriptionSource::Generated->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{customer_description: ?string, customer_description_source: ?string}
     */
    public function forUpdate(RepairOrderLine $line, string $inventoryDescription, array $validated): array
    {
        if (array_key_exists('customer_description', $validated)) {
            return $this->forCreate(
                inventoryDescription: $inventoryDescription,
                explicitCustomerDescription: $validated['customer_description'] ?? null,
            );
        }

        $source = CustomerDescriptionSource::tryFromStored($line->customer_description_source);
        $existing = trim((string) ($line->customer_description ?? ''));
        $priorInventory = trim((string) $line->description);

        if ($source?->isManual() && $existing !== '') {
            return [
                'customer_description' => $line->customer_description,
                'customer_description_source' => CustomerDescriptionSource::Manual->value,
            ];
        }

        if ($existing !== '' && $priorInventory === trim($inventoryDescription) && $source === CustomerDescriptionSource::Generated) {
            return [
                'customer_description' => $line->customer_description,
                'customer_description_source' => CustomerDescriptionSource::Generated->value,
            ];
        }

        return $this->forCreate($inventoryDescription);
    }
}
