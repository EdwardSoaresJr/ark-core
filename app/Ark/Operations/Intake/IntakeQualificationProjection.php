<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class IntakeQualificationProjection
{
    /**
     * @param  list<array{label: string, complete: bool}>  $checklist
     * @param  list<string>  $missingLabels
     */
    public function __construct(
        public string $concernPreview,
        public int $completeCount,
        public int $totalCount,
        public array $checklist,
        public array $missingLabels,
        public string $nextAction,
        public string $workspaceUrl,
        public bool $isReady,
    ) {}

    public function qualificationLabel(): string
    {
        return $this->completeCount.'/'.$this->totalCount.' complete';
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, self>
     */
    public static function mapForRepairOrders(Collection $repairOrders): Collection
    {
        return $repairOrders->mapWithKeys(
            fn (RepairOrder $repairOrder): array => [
                $repairOrder->id => self::forRepairOrder($repairOrder),
            ],
        );
    }

    public static function forRepairOrder(RepairOrder $repairOrder): self
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'concerns', 'lines']);

        $checklist = self::checklist($repairOrder);
        $completeCount = collect($checklist)->where('complete', true)->count();
        $totalCount = count($checklist);
        $missingLabels = collect($checklist)
            ->reject(fn (array $item): bool => $item['complete'])
            ->pluck('label')
            ->values()
            ->all();
        $isReady = $missingLabels === [];

        return new self(
            concernPreview: self::concernPreview($repairOrder),
            completeCount: $completeCount,
            totalCount: $totalCount,
            checklist: $checklist,
            missingLabels: $missingLabels,
            nextAction: self::nextAction($repairOrder, $missingLabels),
            workspaceUrl: self::workspaceUrl($repairOrder, $isReady),
            isReady: $isReady,
        );
    }

    /**
     * @return list<array{label: string, complete: bool}>
     */
    private static function checklist(RepairOrder $repairOrder): array
    {
        $items = [
            [
                'label' => 'Phone',
                'complete' => $repairOrder->customer?->hasPhone() ?? false,
            ],
            [
                'label' => 'VIN',
                'complete' => $repairOrder->vehicle?->hasVin() ?? false,
            ],
            [
                'label' => 'Customer concern',
                'complete' => self::hasConcern($repairOrder),
            ],
            [
                'label' => 'Visit type',
                'complete' => RepairOrderVisitMode::fromRepairOrder($repairOrder) !== null,
            ],
        ];

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            $items[] = [
                'label' => 'Scope opened',
                'complete' => $repairOrder->concerns->isNotEmpty(),
            ];
        } else {
            $items[] = [
                'label' => 'Estimate lines',
                'complete' => $repairOrder->lines->isNotEmpty(),
            ];
        }

        return $items;
    }

    private static function hasConcern(RepairOrder $repairOrder): bool
    {
        if (trim((string) $repairOrder->concern_summary) !== '') {
            return true;
        }

        return $repairOrder->concerns->contains(
            fn ($concern): bool => trim((string) $concern->customer_states) !== '',
        );
    }

    private static function concernPreview(RepairOrder $repairOrder): string
    {
        $summary = trim((string) $repairOrder->concern_summary);

        if ($summary !== '') {
            return Str::limit($summary, 140);
        }

        $fromConcern = $repairOrder->concerns
            ->pluck('customer_states')
            ->map(fn (?string $text): string => trim((string) $text))
            ->filter()
            ->first();

        if ($fromConcern !== null && $fromConcern !== '') {
            return Str::limit($fromConcern, 140);
        }

        return 'Concern not captured yet';
    }

    /**
     * @param  list<string>  $missingLabels
     */
    private static function nextAction(RepairOrder $repairOrder, array $missingLabels): string
    {
        if (in_array('Phone', $missingLabels, true)) {
            return 'Get phone number';
        }

        if (in_array('Customer concern', $missingLabels, true)) {
            return 'Capture customer concern';
        }

        if (in_array('Visit type', $missingLabels, true)) {
            return 'Set visit type';
        }

        if (in_array('VIN', $missingLabels, true)) {
            return 'Add VIN';
        }

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            if (in_array('Scope opened', $missingLabels, true)) {
                return 'Open scope';
            }

            return 'Build estimate';
        }

        if (in_array('Estimate lines', $missingLabels, true)) {
            return 'Add estimate lines';
        }

        return 'Finish estimate';
    }

    private static function workspaceUrl(RepairOrder $repairOrder, bool $isReady): string
    {
        if ($repairOrder->status->is(RepairOrderStatus::Estimate) && $isReady) {
            return route('operations.repair-orders.show', $repairOrder);
        }

        return route('operations.repair-orders.show', $repairOrder);
    }
}
