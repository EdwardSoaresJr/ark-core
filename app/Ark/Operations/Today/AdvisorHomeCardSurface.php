<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Workboard\WorkboardCardActivityMark;

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
        public ?string $whyLabel = null,
        public ?string $moneyLabel = null,
        public ?string $moneyCaption = null,
        public ?string $waitAgeLabel = null,
        public bool $waitingOnCustomerDecision = false,
        public ?string $operationalStatus = null,
        public ?string $clockLabel = null,
        public string $attention = 'normal',
        public bool $statusRestatesLane = false,
        public ?string $configuredStatusColor = null,
        /** @var list<WorkboardCardActivityMark> */
        public array $activityMarks = [],
        public ?string $exceptionLabel = null,
        public int $exceptionExtraCount = 0,
        public string $exceptionTone = 'none',
        /** @var list<array{label: string, tone: string}> */
        public array $exceptionItems = [],
    ) {}
}
