<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeAttentionRow
{
    /**
     * @param  list<AdvisorHomeAttentionDecoration>  $decorations
     */
    public function __construct(
        public int $repairOrderId,
        public string $customerName,
        public string $vehicleLabel,
        public string $statusBadge,
        public ?string $totalLabel,
        public int $totalCents,
        public array $decorations,
        public string $ageLabel,
        public ?string $lastContactLabel,
        public string $href,
        public ?string $customerHubUrl,
        public ?string $textCustomerUrl,
        public ?string $customerPhone,
        public ?string $promiseLabel,
        public bool $isRecommended,
        public string $statusChipTone,
        public ?string $staleLevel,
        public int $urgencyScore,
        public string $homeSearch,
        public ?int $assignedTechnicianId,
        public int $ageDays,
        public ?string $attentionReason,
    ) {}
}
