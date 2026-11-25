<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Inspections\InspectionPosture;
use App\Ark\Operations\Inspections\InspectionPostureProjection;
use App\Ark\Operations\Inspections\InspectionRequirementResolver;

/**
 * Operational unfinished work for finish / pickup / paid-close accountability.
 * Does not rewrite inspection, labor, or procurement state. Financial rules stay separate.
 */
final class RepairOrderOperationalOutstandingWork
{
    public const KIND_INSPECTION_IN_PROGRESS = 'inspection_in_progress';

    public const KIND_PART_NOT_RECEIVED = 'part_not_received';

    public const KIND_PART_NOT_INSTALLED = 'part_not_installed';

    public const KIND_LABOR_INCOMPLETE = 'labor_incomplete';

    /**
     * Owner policy: gate finish / pickup / paid close only.
     * Quality Check stays a normal operational move (authorization integrity tests rely on that).
     *
     * @var list<string>
     */
    private const GATED_STATUSES = [
        'completed',
        'ready_pickup',
    ];

    public function __construct(
        private readonly InspectionPostureProjection $inspectionPosture,
        private readonly InspectionRequirementResolver $inspectionRequirement,
    ) {}

    public function appliesTo(string $toStatusSlug, ?string $closeVariantKey = null): bool
    {
        if (in_array($toStatusSlug, self::GATED_STATUSES, true)) {
            return true;
        }

        return $toStatusSlug === RepairOrderStatus::Closed->value
            && $closeVariantKey === 'paid';
    }

    /**
     * @return list<array{
     *     key: string,
     *     kind: string,
     *     title: string,
     *     detail: string
     * }>
     */
    public function items(RepairOrder $repairOrder): array
    {
        $items = [];

        $inspection = $this->inspectionItem($repairOrder);

        if ($inspection !== null) {
            $items[] = $inspection;
        }

        foreach ($this->partItems($repairOrder) as $item) {
            $items[] = $item;
        }

        foreach ($this->laborItems($repairOrder) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    public function fingerprint(RepairOrder $repairOrder): string
    {
        $keys = array_map(
            static fn (array $item): string => $item['key'],
            $this->items($repairOrder),
        );
        sort($keys);

        return hash('sha256', implode('|', $keys));
    }

    public function hasOutstanding(RepairOrder $repairOrder): bool
    {
        return $this->items($repairOrder) !== [];
    }

    /**
     * @return array{key: string, kind: string, title: string, detail: string}|null
     */
    private function inspectionItem(RepairOrder $repairOrder): ?array
    {
        $decision = $this->inspectionRequirement->forRepairOrder($repairOrder);

        // Optional / Recommended / waived: never a closeout unfinished-work item.
        // Required incomplete coverage (never started or in progress) remains a controlled gate.
        if (! $decision->blocksLifecycleOnIncomplete()) {
            return null;
        }

        $posture = $this->inspectionPosture->forRepairOrder($repairOrder);

        if ($posture->key === InspectionPosture::COMPLETE
            || $posture->key === InspectionPosture::NEEDS_REVIEW) {
            return null;
        }

        $template = $posture->templateName ?: 'Inspection';

        if (! $posture->started || $posture->key === InspectionPosture::NOT_STARTED) {
            return [
                'key' => 'inspection:required_not_started',
                'kind' => self::KIND_INSPECTION_IN_PROGRESS,
                'title' => 'Inspection required',
                'detail' => $template.' — required by '.$decision->requirement->label()
                    .' policy; not started. '.$decision->detail,
            ];
        }

        if ($posture->key !== InspectionPosture::IN_PROGRESS) {
            return null;
        }

        $detail = $posture->detail ?: ($posture->checked.' of '.$posture->total.' items covered');

        return [
            'key' => 'inspection:in_progress',
            'kind' => self::KIND_INSPECTION_IN_PROGRESS,
            'title' => 'Inspection required',
            'detail' => $template.' — '.$detail.'. Required by shop/RO policy.',
        ];
    }

    /**
     * @return list<array{key: string, kind: string, title: string, detail: string}>
     */
    private function partItems(RepairOrder $repairOrder): array
    {
        return $repairOrder->approvedPartLines()
            ->filter(function (RepairOrderLine $line): bool {
                $state = $line->procurementState();

                return $state !== PartProcurementState::Installed
                    && $state !== PartProcurementState::Canceled;
            })
            ->map(function (RepairOrderLine $line): array {
                $state = $line->procurementState();
                $label = trim((string) $line->description) ?: 'Part';

                if ($state === PartProcurementState::Received) {
                    return [
                        'key' => 'part:'.$line->id.':not_installed',
                        'kind' => self::KIND_PART_NOT_INSTALLED,
                        'title' => 'Parts not installed',
                        'detail' => $label.' — received, not installed',
                    ];
                }

                return [
                    'key' => 'part:'.$line->id.':not_received',
                    'kind' => self::KIND_PART_NOT_RECEIVED,
                    'title' => 'Parts not received',
                    'detail' => $label.' — '.$state->pressureLabel($line->part_source).', not received',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, kind: string, title: string, detail: string}>
     */
    private function laborItems(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('concerns');

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->productionStatus() !== ScopeProductionStatus::Completed)
            ->map(function (RepairOrderConcern $concern): array {
                $summary = trim((string) $concern->summary) ?: 'Approved concern';
                $status = $concern->productionStatus();

                return [
                    'key' => 'labor:'.$concern->id,
                    'kind' => self::KIND_LABOR_INCOMPLETE,
                    'title' => 'Approved labor not complete',
                    'detail' => $summary.' — '.$status->workStatusLabel(),
                ];
            })
            ->values()
            ->all();
    }
}
