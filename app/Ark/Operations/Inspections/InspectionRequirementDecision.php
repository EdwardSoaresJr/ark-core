<?php

namespace App\Ark\Operations\Inspections;

/**
 * Resolved inspection obligation for one RO — not coverage, not evidence.
 *
 * @phpstan-type Source 'shop_default'|'shop_setting'|'repair_order_override'|'waived'
 */
final readonly class InspectionRequirementDecision
{
    /**
     * @param  Source  $source
     */
    public function __construct(
        public InspectionRequirement $requirement,
        public string $source,
        public bool $waived = false,
        public ?string $waiveReason = null,
        public ?string $detail = null,
    ) {}

    public function blocksLifecycleOnIncomplete(): bool
    {
        if ($this->waived) {
            return false;
        }

        return $this->requirement->blocksLifecycleOnIncomplete();
    }

    /**
     * @return array{
     *     requirement: string,
     *     requirement_label: string,
     *     source: string,
     *     waived: bool,
     *     waive_reason: ?string,
     *     detail: ?string,
     *     blocks_lifecycle_on_incomplete: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'requirement' => $this->requirement->value,
            'requirement_label' => $this->requirement->label(),
            'source' => $this->source,
            'waived' => $this->waived,
            'waive_reason' => $this->waiveReason,
            'detail' => $this->detail,
            'blocks_lifecycle_on_incomplete' => $this->blocksLifecycleOnIncomplete(),
        ];
    }
}
