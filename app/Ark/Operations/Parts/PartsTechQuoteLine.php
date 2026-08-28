<?php

namespace App\Ark\Operations\Parts;

final readonly class PartsTechQuoteLine
{
    public function __construct(
        public string $sourceKey,
        public string $description,
        public string $quantity,
        public string $partCost,
        public ?string $partNumber,
        public ?string $vendorName,
        public ?string $sourcingNotes,
        public ?string $positionLabel = null,
    ) {}

    /**
     * @return array{
     *     source_key: string,
     *     description: string,
     *     quantity: string,
     *     part_cost: string,
     *     part_number: ?string,
     *     vendor_name: ?string,
     *     position_label: ?string,
     * }
     */
    public function toAssignmentPayload(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'part_cost' => $this->partCost,
            'part_number' => $this->partNumber,
            'vendor_name' => $this->vendorName,
            'position_label' => $this->positionLabel,
        ];
    }
}
