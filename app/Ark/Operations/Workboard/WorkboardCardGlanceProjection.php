<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationLifecycle;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Stage / operational status / attention for an open workboard card.
 * Derives from RO + comms + ledger + timestamps. Does not invent RO status.
 */
final class WorkboardCardGlanceProjection
{
    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  array<int, WorkboardTriageCard>  $cardsByRepairOrderId
     * @param  Collection<int, EstimateTotals>  $totals
     * @param  array<int, BalanceDueResult>  $balances
     * @param  array<int, string>  $columnKeyByRepairOrderId
     * @return array<int, WorkboardCardGlance>
     */
    public function map(
        Collection $repairOrders,
        array $cardsByRepairOrderId,
        Collection $totals,
        array $balances,
        array $columnKeyByRepairOrderId,
    ): array {
        $statusEnteredAt = $this->statusEnteredAtByRepairOrderId($repairOrders);
        $dueFollowUpVehicleIds = $this->dueRecommendationFollowUpVehicleIds($repairOrders);
        $glances = [];

        foreach ($repairOrders as $repairOrder) {
            $card = $cardsByRepairOrderId[$repairOrder->id] ?? null;

            if (! $card instanceof WorkboardTriageCard) {
                continue;
            }

            $glances[$repairOrder->id] = $this->forCard(
                $card,
                $columnKeyByRepairOrderId[$repairOrder->id] ?? '',
                $totals[$repairOrder->id] ?? null,
                $balances[$repairOrder->id] ?? null,
                $statusEnteredAt[$repairOrder->id] ?? null,
                isset($dueFollowUpVehicleIds[(int) $repairOrder->vehicle_id]),
            );
        }

        return $glances;
    }

    public function forCard(
        WorkboardTriageCard $card,
        string $columnKey,
        ?EstimateTotals $totals,
        ?BalanceDueResult $balance,
        ?Carbon $statusEnteredAt,
        bool $hasDueRecommendationFollowUp,
    ): WorkboardCardGlance {
        $repairOrder = $card->repairOrder;
        $waitingOnDecision = $this->waitingOnCustomerDecision($card, $totals);
        $waitStartedAt = $this->waitStartedAt($card, $waitingOnDecision, $statusEnteredAt);
        [$moneyLabel, $moneyCaption] = $this->money($card, $columnKey, $totals, $balance, $waitingOnDecision);
        $conditionStatus = $this->conditionStatus($card, $columnKey, $waitingOnDecision, $balance, $hasDueRecommendationFollowUp);
        $configured = $this->configuredStatusLabel($card->repairOrder)
            ?? $card->repairOrder->status->label();
        $operationalStatus = filled($configured) ? $configured : 'No Status';
        $configuredStatusColor = $this->configuredStatusColor($card);

        return new WorkboardCardGlance(
            whyLabel: $this->why($card, $columnKey, $waitingOnDecision, $hasDueRecommendationFollowUp),
            nextLabel: $this->next($card, $columnKey, $waitingOnDecision, $balance, $hasDueRecommendationFollowUp),
            moneyLabel: $moneyLabel,
            moneyCaption: $moneyCaption,
            ageLabel: $this->formatAge($waitStartedAt),
            waitingOnCustomerDecision: $waitingOnDecision,
            operationalStatus: $operationalStatus,
            clockLabel: $this->formatAge($waitStartedAt),
            attention: $this->attention($card, $waitingOnDecision, $waitStartedAt, $hasDueRecommendationFollowUp, $conditionStatus),
            statusRestatesLane: $this->statusRestatesLane($columnKey, $operationalStatus),
            configuredStatusColor: $configuredStatusColor,
        );
    }

    private function waitingOnCustomerDecision(WorkboardTriageCard $card, ?EstimateTotals $totals): bool
    {
        if ($card->repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return true;
        }

        $signal = (string) $card->signalLabel;

        if ($signal !== '' && (
            str_contains($signal, 'Estimate Viewed')
            || str_contains($signal, 'Estimate Sent')
        )) {
            return ($totals?->totalCents() ?? 0) > 0
                && $card->repairOrder->status->is(RepairOrderStatus::Estimate);
        }

        return false;
    }

    private function why(
        WorkboardTriageCard $card,
        string $columnKey,
        bool $waitingOnDecision,
        bool $hasDueRecommendationFollowUp,
    ): string {
        if ($card->countsAsOverduePickup) {
            return 'Overdue pickup';
        }

        if ($waitingOnDecision) {
            return $this->decisionWhy($card);
        }

        if (is_string($card->signalLabel) && $card->signalLabel !== '') {
            if (str_contains($card->signalLabel, 'Waiting on Parts') || str_contains($card->signalLabel, 'Waiting Parts')) {
                return $card->signalLabel;
            }

            if (str_contains($card->signalLabel, 'Unassigned')) {
                return 'Unassigned tech';
            }

            if (str_contains($card->signalLabel, 'Vehicle ID')) {
                return 'Vehicle ID needed';
            }

            if (str_contains($card->signalLabel, 'Customer Waiting') || str_contains($card->signalLabel, 'Multiple Customer')) {
                return 'Customer waiting on shop';
            }
        }

        if ($hasDueRecommendationFollowUp) {
            return 'Deferred work due';
        }

        return match ($columnKey) {
            'estimates' => $card->repairOrder->status->is(RepairOrderStatus::Draft)
                ? 'Needs diagnosis'
                : 'Estimate not finished',
            'waiting_approval' => 'Waiting on decision',
            'parts' => 'Waiting on parts',
            'work_in_progress' => $card->countsAsUnassigned ? 'Unassigned tech' : 'Work in progress',
            'completed' => 'Ready for pickup',
            default => filled($card->concernHeadline) && $card->concernHeadline !== 'No concern recorded'
                ? $card->concernHeadline
                : 'Open work',
        };
    }

    private function decisionWhy(WorkboardTriageCard $card): string
    {
        $signal = (string) $card->signalLabel;

        if (str_contains($signal, 'Estimate Viewed')) {
            return 'Waiting on decision · viewed';
        }

        if (str_contains($signal, 'Customer Waiting') || str_contains($signal, 'Multiple Customer')) {
            return 'Customer waiting on shop';
        }

        if ($this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateViewed)) {
            return 'Waiting on decision · viewed';
        }

        if (str_contains($signal, 'Estimate Sent') || $this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateSent)) {
            return 'Waiting on decision · sent';
        }

        return 'Waiting on decision · not sent';
    }

    private function next(
        WorkboardTriageCard $card,
        string $columnKey,
        bool $waitingOnDecision,
        ?BalanceDueResult $balance,
        bool $hasDueRecommendationFollowUp,
    ): string {
        if ($card->countsAsOverduePickup) {
            return 'Call for pickup';
        }

        if ($balance instanceof BalanceDueResult && $balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
            return 'Collect balance';
        }

        if ($waitingOnDecision) {
            if ($this->hasEvent($card->repairOrder, OperationalCommunicationType::CustomerReply)
                || (is_string($card->signalLabel) && str_contains((string) $card->signalLabel, 'Customer Waiting'))) {
                return 'Respond';
            }

            if ($this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateSent)
                || $this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateViewed)) {
                return 'Follow up';
            }

            return 'Send estimate';
        }

        if ($hasDueRecommendationFollowUp) {
            return 'Present recommendation';
        }

        if ($columnKey === 'work_in_progress' && (
            $card->repairOrder->status->is(RepairOrderStatus::WaitingParts)
            || (is_string($card->signalLabel) && (
                str_contains($card->signalLabel, 'Waiting on Parts')
                || str_contains($card->signalLabel, 'Waiting Parts')
            ))
        )) {
            return 'Check parts';
        }

        $fromSignal = $card->nextMoveLabel();

        if (is_string($fromSignal) && $fromSignal !== '') {
            return $fromSignal;
        }

        if ($card->countsAsUnassigned) {
            return 'Assign tech';
        }

        return match ($columnKey) {
            'estimates' => $card->repairOrder->status->is(RepairOrderStatus::Draft) ? 'Diagnose' : 'Finish estimate',
            'waiting_approval' => 'Follow up',
            'parts' => 'Check parts',
            'work_in_progress' => 'Work active',
            'completed' => 'Close or collect',
            default => 'Review',
        };
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function money(
        WorkboardTriageCard $card,
        string $columnKey,
        ?EstimateTotals $totals,
        ?BalanceDueResult $balance,
        bool $waitingOnDecision,
    ): array {
        if ($balance instanceof BalanceDueResult && $balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
            return [$this->dollars($balance->balanceDueCents), 'due'];
        }

        $totalCents = $totals?->totalCents() ?? 0;

        if ($waitingOnDecision && $totalCents > 0) {
            return [$this->dollars($totalCents), 'pending'];
        }

        return [$this->dollars($totalCents), null];
    }

    private function waitStartedAt(
        WorkboardTriageCard $card,
        bool $waitingOnDecision,
        ?Carbon $statusEnteredAt,
    ): Carbon {
        $repairOrder = $card->repairOrder;

        if ($waitingOnDecision) {
            $viewed = $this->latestEventAt($repairOrder, OperationalCommunicationType::EstimateViewed);
            $sent = $this->latestEventAt($repairOrder, OperationalCommunicationType::EstimateSent);

            return $viewed
                ?? $sent
                ?? $statusEnteredAt
                ?? $this->timestampOrNow($repairOrder->updated_at);
        }

        return $statusEnteredAt ?? $this->timestampOrNow($repairOrder->updated_at);
    }

    private function timestampOrNow(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        return now();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, Carbon>
     */
    private function statusEnteredAtByRepairOrderId(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $events = OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->whereIn('aggregate_id', $repairOrders->modelKeys())
            ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['aggregate_id', 'occurred_at', 'payload_json']);

        $enteredAt = [];

        foreach ($events as $event) {
            $toStatus = $event->payload_json['to_status'] ?? null;
            $repairOrder = $repairOrders->firstWhere('id', $event->aggregate_id);

            if (! $repairOrder instanceof RepairOrder || $toStatus !== $repairOrder->status->value) {
                continue;
            }

            $enteredAt[(int) $event->aggregate_id] = $event->occurred_at;
        }

        return $enteredAt;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, true>
     */
    private function dueRecommendationFollowUpVehicleIds(Collection $repairOrders): array
    {
        $vehicleIds = $repairOrders->pluck('vehicle_id')->filter()->unique()->values();

        if ($vehicleIds->isEmpty()) {
            return [];
        }

        $due = [];

        foreach (
            Recommendation::query()
                ->whereIn('vehicle_id', $vehicleIds)
                ->where('lifecycle', RecommendationLifecycle::Open)
                ->whereNotNull('follow_up_at')
                ->whereNull('follow_up_completed_at')
                ->get(['id', 'vehicle_id', 'follow_up_at', 'follow_up_completed_at', 'follow_up_snoozed_until']) as $recommendation
        ) {
            if ($recommendation->followUpIsDue()) {
                $due[(int) $recommendation->vehicle_id] = true;
            }
        }

        return $due;
    }

    private function hasEvent(RepairOrder $repairOrder, OperationalCommunicationType $type): bool
    {
        if (! $repairOrder->relationLoaded('communicationEvents')) {
            return false;
        }

        return $repairOrder->communicationEvents->contains(
            fn (CommunicationEvent $event): bool => $event->event_type === $type,
        );
    }

    private function latestEventAt(RepairOrder $repairOrder, OperationalCommunicationType $type): ?Carbon
    {
        if (! $repairOrder->relationLoaded('communicationEvents')) {
            return null;
        }

        $event = $repairOrder->communicationEvents
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === $type)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();

        return $event?->occurred_at;
    }

    private function dollars(int $cents): string
    {
        return '$'.number_format($cents / 100, 0);
    }

    private function conditionStatus(
        WorkboardTriageCard $card,
        string $columnKey,
        bool $waitingOnDecision,
        ?BalanceDueResult $balance,
        bool $hasDueRecommendationFollowUp,
    ): string {
        if ($balance instanceof BalanceDueResult && $balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
            return 'Balance Due';
        }

        if ($card->countsAsOverduePickup) {
            return 'Ready Pickup';
        }

        $signal = (string) $card->signalLabel;

        if ($waitingOnDecision) {
            if (str_contains($signal, 'Estimate Viewed') || $this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateViewed)) {
                return 'Viewed';
            }

            if (str_contains($signal, 'Estimate Sent') || $this->hasEvent($card->repairOrder, OperationalCommunicationType::EstimateSent)) {
                return 'Sent';
            }

            return 'Not Sent';
        }

        $partsStatus = $this->partsOperationalStatus($signal);

        if ($partsStatus !== null) {
            return $partsStatus;
        }

        if ($card->countsAsUnassigned) {
            return 'Unassigned';
        }

        if ($card->repairOrder->status->is(RepairOrderStatus::WaitingParts)
            || str_contains($signal, 'Waiting on Parts')
            || str_contains($signal, 'Waiting Parts')) {
            return 'Waiting Parts';
        }

        if ($hasDueRecommendationFollowUp) {
            return 'Follow-up due';
        }

        if ($card->repairOrder->status->is(RepairOrderStatus::Draft)) {
            return 'Needs Diagnosis';
        }

        if ($card->repairOrder->status->is(RepairOrderStatus::Estimate)) {
            return 'Building';
        }

        if ($card->repairOrder->status->is(RepairOrderStatus::QualityCheck)) {
            return $this->configuredStatusLabel($card->repairOrder) ?? 'Quality Check';
        }

        if ($card->repairOrder->status->isOneOf([
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
        ])) {
            return $this->configuredStatusLabel($card->repairOrder) ?? 'Ready for Work';
        }

        if ($card->repairOrder->status->isOneOf([
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ])) {
            return $this->configuredStatusLabel($card->repairOrder) ?? 'Ready Pickup';
        }

        if ($signal !== '' && str_contains($signal, 'Vehicle ID')) {
            return 'Vehicle ID';
        }

        if ($columnKey === 'work_in_progress' || $card->repairOrder->status->is(RepairOrderStatus::InProgress)) {
            return $this->configuredStatusLabel($card->repairOrder) ?? 'In Progress';
        }

        return $this->configuredStatusLabel($card->repairOrder) ?? 'Building';
    }

    private function statusRestatesLane(string $columnKey, string $operationalStatus): bool
    {
        $laneName = app(JobBoardLaneCatalog::class)->labelForKey($columnKey);

        if (is_string($laneName) && strcasecmp($operationalStatus, $laneName) === 0) {
            return true;
        }

        return match ($columnKey) {
            JobBoardLaneCatalogDefaults::WAITING_APPROVAL => $operationalStatus === 'Waiting Approval',
            JobBoardLaneCatalogDefaults::PARTS => $operationalStatus === 'Waiting Parts',
            JobBoardLaneCatalogDefaults::COMPLETED => in_array($operationalStatus, ['Ready Pickup', 'Ready for Pickup'], true),
            default => false,
        };
    }

    private function configuredStatusLabel(RepairOrder $repairOrder): ?string
    {
        $catalog = app(RepairOrderStatusCatalog::class);
        $slug = $repairOrder->workboardLaneStatus()->value;

        if ($catalog->isBooted()) {
            return $catalog->labelForSlug($slug);
        }

        return RepairOrderStatus::tryFrom($slug)?->label();
    }

    private function configuredStatusColor(WorkboardTriageCard $card): ?string
    {
        $catalog = app(RepairOrderStatusCatalog::class);
        $definition = $catalog->definitionForSlug($card->repairOrder->workboardLaneStatus()->value);

        if ($definition === null || ! filled($definition->color)) {
            return null;
        }

        return RepairOrderStatusColor::normalize($definition->color);
    }

    private function partsOperationalStatus(string $signal): ?string
    {
        if ($signal === '') {
            return null;
        }

        if (str_contains($signal, 'All Parts Available') || str_contains($signal, 'Parts Ready')) {
            return 'Parts Ready';
        }

        if (str_contains($signal, 'Backordered') || str_contains($signal, 'Partial Parts') || str_contains($signal, 'Needs Parts')) {
            return 'Needs Parts';
        }

        if (str_contains($signal, 'Waiting on Parts') || str_contains($signal, 'Waiting Parts')) {
            return 'Waiting Parts';
        }

        return null;
    }

    private function attention(
        WorkboardTriageCard $card,
        bool $waitingOnDecision,
        Carbon $waitStartedAt,
        bool $hasDueRecommendationFollowUp,
        string $operationalStatus,
    ): string {
        $hours = (int) abs($waitStartedAt->diffInHours(now()));

        if ($card->countsAsOverduePickup) {
            return WorkboardCardGlance::ATTENTION_CRITICAL;
        }

        if ($waitingOnDecision && $hours >= 168) {
            return WorkboardCardGlance::ATTENTION_CRITICAL;
        }

        if ($waitingOnDecision && $hours >= 48) {
            return WorkboardCardGlance::ATTENTION_ATTENTION;
        }

        if ($waitingOnDecision && $operationalStatus === 'Not Sent' && $hours >= 24) {
            return WorkboardCardGlance::ATTENTION_ATTENTION;
        }

        if ($hasDueRecommendationFollowUp || $card->countsAsUnassigned || $card->countsAsCustomerWaiting) {
            return WorkboardCardGlance::ATTENTION_ATTENTION;
        }

        if ($operationalStatus === 'Waiting Parts' && $hours >= 48) {
            return WorkboardCardGlance::ATTENTION_ATTENTION;
        }

        if ($waitingOnDecision || in_array($operationalStatus, ['Waiting Parts', 'Needs Parts', 'Vehicle ID'], true)) {
            return WorkboardCardGlance::ATTENTION_WATCH;
        }

        if (is_string($card->signalLabel) && str_contains($card->signalLabel, 'Vehicle ID')) {
            return WorkboardCardGlance::ATTENTION_WATCH;
        }

        return WorkboardCardGlance::ATTENTION_NORMAL;
    }

    private function formatAge(Carbon $from): string
    {
        return str_replace(' ago', '', $from->copy()->diffForHumans(short: true, parts: 1));
    }
}
