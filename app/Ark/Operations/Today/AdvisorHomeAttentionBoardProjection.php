<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use Illuminate\Support\Collection;

final class AdvisorHomeAttentionBoardProjection
{
    public function __construct(
        private readonly WorkboardTriageProjection $triageProjection,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  array<int, AdvisorHomeCardSurface>  $cardSurfaces
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     * @return list<AdvisorHomeAttentionZone>
     */
    public function zones(
        Collection $repairOrders,
        array $cardSurfaces,
        Collection $repairOrderTotals,
        ?int $recommendedRepairOrderId = null,
    ): array {
        $cards = $this->triageProjection->cardsForRepairOrders($repairOrders);

        /** @var array<string, list<AdvisorHomeAttentionRow>> $rowsByZone */
        $rowsByZone = [
            AdvisorHomeAttentionZoneKey::NeedsAction->value => [],
            AdvisorHomeAttentionZoneKey::ActiveWork->value => [],
            AdvisorHomeAttentionZoneKey::ReadyPickup->value => [],
        ];

        foreach ($cards as $card) {
            $repairOrder = $card->repairOrder;
            $totals = $repairOrderTotals[$repairOrder->id] ?? null;
            $totalCents = $totals instanceof EstimateTotals ? $totals->totalCents() : 0;
            $surface = $cardSurfaces[$repairOrder->id] ?? null;
            $observations = $this->triageProjection->observationsFor($repairOrder);
            $zoneKey = $this->zoneKeyFor($card, $surface);

            $rowsByZone[$zoneKey->value][] = $this->rowFor(
                card: $card,
                totalCents: $totalCents,
                totals: $totals,
                observations: $observations,
                surface: $surface,
                zoneKey: $zoneKey,
                recommendedRepairOrderId: $recommendedRepairOrderId,
            );
        }

        foreach ($rowsByZone as $zoneValue => $rows) {
            usort(
                $rows,
                fn (AdvisorHomeAttentionRow $left, AdvisorHomeAttentionRow $right): int => $right->urgencyScore <=> $left->urgencyScore
                    ?: $right->totalCents <=> $left->totalCents
                    ?: strcmp($left->customerName, $right->customerName),
            );

            $rowsByZone[$zoneValue] = $rows;
        }

        return array_map(
            fn (AdvisorHomeAttentionZoneKey $zoneKey): AdvisorHomeAttentionZone => new AdvisorHomeAttentionZone(
                key: $zoneKey,
                label: $zoneKey->label(),
                count: count($rowsByZone[$zoneKey->value]),
                rows: $rowsByZone[$zoneKey->value],
            ),
            [
                AdvisorHomeAttentionZoneKey::NeedsAction,
                AdvisorHomeAttentionZoneKey::ActiveWork,
                AdvisorHomeAttentionZoneKey::ReadyPickup,
            ],
        );
    }

    /**
     * @param  list<AdvisorHomeAttentionZone>  $zones
     * @return list<AdvisorHomeAttentionRow>
     */
    public function allRows(array $zones): array
    {
        return array_merge(
            ...array_map(
                fn (AdvisorHomeAttentionZone $zone): array => $zone->rows,
                $zones,
            ),
        );
    }

    private function zoneKeyFor(WorkboardTriageCard $card, ?AdvisorHomeCardSurface $surface): AdvisorHomeAttentionZoneKey
    {
        $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($card->repairOrder);

        if (AdvisorHomeActionableAttention::belongsInNeedsAction($card, $surface)) {
            return AdvisorHomeAttentionZoneKey::NeedsAction;
        }

        if ($laneKey === 'ready_pickup') {
            return AdvisorHomeAttentionZoneKey::ReadyPickup;
        }

        return AdvisorHomeAttentionZoneKey::ActiveWork;
    }

    /**
     * @param  list<OperationalObservation>  $observations
     */
    private function rowFor(
        WorkboardTriageCard $card,
        int $totalCents,
        ?EstimateTotals $totals,
        array $observations,
        ?AdvisorHomeCardSurface $surface,
        AdvisorHomeAttentionZoneKey $zoneKey,
        ?int $recommendedRepairOrderId,
    ): AdvisorHomeAttentionRow {
        $repairOrder = $card->repairOrder;
        $customerName = trim((string) ($repairOrder->customer?->name ?? ''));

        if ($customerName === '') {
            $customerName = 'Unknown customer';
        }

        $totalLabel = $totalCents > 0 && $totals instanceof EstimateTotals
            ? $totals->format($totalCents)
            : null;

        $attentionReason = AdvisorHomeAttentionReason::for($zoneKey, $card, $surface);

        $homeSearch = strtolower(collect([
            $repairOrder->repair_order_id,
            $customerName,
            $card->vehicleLabel,
            $surface?->customerPhone,
            $repairOrder->statusDisplayLabel(),
            $attentionReason,
        ])->filter()->join(' '));

        $ageDays = max(0, (int) $repairOrder->created_at->diffInDays());

        return new AdvisorHomeAttentionRow(
            repairOrderId: $repairOrder->repair_order_id,
            customerName: $customerName,
            vehicleLabel: $card->vehicleLabel,
            statusBadge: $repairOrder->statusDisplayLabel(),
            totalLabel: $totalLabel,
            totalCents: $totalCents,
            decorations: [],
            ageLabel: $this->ageLabel($repairOrder),
            lastContactLabel: null,
            href: $card->href.'#builder',
            customerHubUrl: $surface?->customerHubUrl,
            textCustomerUrl: $surface?->textCustomerUrl,
            customerPhone: $surface?->customerPhone,
            promiseLabel: null,
            isRecommended: $recommendedRepairOrderId !== null
                && $recommendedRepairOrderId === $repairOrder->repair_order_id,
            statusChipTone: AdvisorHomeAttentionStatusChipTone::forRepairOrder($repairOrder),
            staleLevel: $this->staleLevel($ageDays),
            urgencyScore: $card->homeUrgencyScore($totalCents),
            homeSearch: $homeSearch,
            assignedTechnicianId: $repairOrder->assigned_technician_id,
            ageDays: $ageDays,
            attentionReason: $attentionReason,
        );
    }

    private function staleLevel(int $ageDays): ?string
    {
        if ($ageDays >= 30) {
            return 'critical';
        }

        if ($ageDays >= 14) {
            return 'warn';
        }

        return null;
    }

    private function ageLabel(RepairOrder $repairOrder): string
    {
        $days = max(0, (int) $repairOrder->created_at->diffInDays());

        if ($days === 0) {
            return 'Opened today';
        }

        if ($days === 1) {
            return '1 day old';
        }

        return $days.' days old';
    }
}
