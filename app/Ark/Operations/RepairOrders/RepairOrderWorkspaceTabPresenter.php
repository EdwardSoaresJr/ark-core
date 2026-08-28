<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use Illuminate\Http\Request;

final class RepairOrderWorkspaceTabPresenter
{
    /** @var list<string> */
    public const TABS = ['comms', 'portal', 'auth', 'parts', 'history', 'inspect'];

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly ConversationTimeline $conversationTimeline,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly UnifiedOperationalTimeline $unifiedTimeline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Request $request, RepairOrder $repairOrder, string $tab, string $workspaceMode): array
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        $totals = $this->calculator->totalsFor($repairOrder);
        $settings = ShopSettings::current();
        $isTerminal = $repairOrder->isTerminal();

        $shared = [
            'repairOrder' => $repairOrder,
            'totals' => $totals,
            'estimateVersion' => app(RepairOrderConcurrency::class)->openedVersion($repairOrder),
            'isTerminal' => $isTerminal,
            'workspaceMode' => $workspaceMode,
            'taxLabel' => $settings->taxLabel(),
        ];

        return match ($tab) {
            'comms' => [
                ...$shared,
                ...$this->commsData($repairOrder),
                'railMode' => $workspaceMode === 'builder' ? 'builder' : 'review',
            ],
            'portal' => $shared,
            'auth' => $shared,
            'parts' => [
                ...$shared,
                ...RepairOrderPosture::for($repairOrder),
                'railMode' => $workspaceMode === 'builder' ? 'builder' : 'review',
            ],
            'history' => [
                ...$shared,
                ...$this->historyData($repairOrder),
            ],
            'inspect' => [
                ...$shared,
                ...$this->inspectData($request, $repairOrder),
            ],
            default => $shared,
        };
    }

    public function loadRepairOrderForTab(RepairOrder $repairOrder, string $tab): RepairOrder
    {
        $relations = match ($tab) {
            'comms' => [
                'customer',
                'operationalCommitments.owner',
            ],
            'portal' => [
                'customer',
                'vehicle',
                'lines',
            ],
            'auth' => [
                'approvalEvents.revocation.recordedBy',
            ],
            'parts' => [
                'concerns.lines',
            ],
            'history' => [
                'vehicle.repairOrders' => fn ($repairOrders) => $repairOrders
                    ->with(['concerns.lines', 'lines'])
                    ->whereKeyNot($repairOrder->id)
                    ->latest()
                    ->limit(8),
            ],
            default => [],
        };

        if ($relations !== []) {
            $repairOrder->load($relations);
        }

        return $repairOrder;
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectData(Request $request, RepairOrder $repairOrder): array
    {
        return [
            ...\App\Ark\Operations\Inspections\InspectionWorkspaceTabBadgeProjection::for($repairOrder, $request->user()),
            'inspectionControl' => app(\App\Ark\Operations\Inspections\InspectionControlCenterProjection::class)
                ->for($repairOrder, $request->user()),
            'canRecordFindings' => \App\Ark\Operations\Inspections\InspectionCaptureLinks::canRecord($request->user(), $repairOrder),
            'identity' => OperationalIdentityPresenter::forRepairOrder($repairOrder, includeStaffPosture: true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commsData(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer']);

        $linkedMessages = $this->conversationTimeline->forRepairOrder($repairOrder, 1);

        return [
            'timelineEvents' => $this->unifiedTimeline->forRepairOrderRelationship($repairOrder, 50),
            'hasConversationHistory' => $linkedMessages->isNotEmpty(),
            'customerCallContext' => $repairOrder->customer
                ? $this->callContextResolver->resolveForCustomer($repairOrder->customer)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyData(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        return [
            'customerCallContext' => $repairOrder->customer
                ? $this->callContextResolver->resolveForCustomer($repairOrder->customer)
                : null,
        ];
    }
}
