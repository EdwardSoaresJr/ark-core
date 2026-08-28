<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeCardSurface
{
    public function __construct(
        public AdvisorHomeCardChip $chip,
        public ?string $customerPhone,
        public ?string $techInitials,
        public ?string $promiseLabel,
        public string $promiseTone,
        public bool $vehicleOnSite,
        public ?AdvisorHomeLaborProgress $laborProgress,
        public ?string $customerHubUrl,
        public ?string $textCustomerUrl,
        public ?string $recordFindingUrl,
        public ?string $estimateEventLabel = null,
        public ?string $estimateEventKind = null,
        /**
         * Same choices as the RO lifecycle select (status + close), via
         * RepairOrderLifecycleSelectProjection::boardMoves().
         *
         * @var list<array{
         *     value: string,
         *     label: string,
         *     disabled: bool,
         *     blockedReason: ?string,
         *     needsRoConfirmation: bool
         * }>
         */
        public array $statusMoves = [],
        public ?string $concernLabel = null,
        public ?string $nextMoveLabel = null,
        public ?string $scheduleLabel = null,
        public string $scheduleTone = 'none',
    ) {}
}
