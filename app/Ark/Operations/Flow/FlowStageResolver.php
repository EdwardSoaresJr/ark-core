<?php

namespace App\Ark\Operations\Flow;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;

final class FlowStageResolver
{
    public function stageKeyFor(RepairOrder $repairOrder): ?FlowStageKey
    {
        $status = RepairOrderWorkflowStatus::from($repairOrder->status);

        if ($status->is(RepairOrderStatus::Closed) || $status->isTerminal()) {
            return null;
        }

        $hasLines = $this->hasEstimateLines($repairOrder);

        return match ($status->value) {
            RepairOrderStatus::Draft->value => $hasLines
                ? FlowStageKey::NeedsDiagnosis
                : FlowStageKey::WorkArrives,
            RepairOrderStatus::Estimate->value => $hasLines
                ? FlowStageKey::BuildingEstimate
                : FlowStageKey::WorkArrives,
            RepairOrderStatus::WaitingApproval->value => FlowStageKey::WaitingApproval,
            RepairOrderStatus::WaitingParts->value => FlowStageKey::WaitingParts,
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::InProgress->value => FlowStageKey::InRepair,
            RepairOrderStatus::QualityCheck->value => FlowStageKey::QualityCheck,
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::Invoiced->value,
            RepairOrderStatus::ReadyPickup->value => FlowStageKey::ReadyPickup,
            default => null,
        };
    }

    private function hasEstimateLines(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->relationLoaded('lines')) {
            return $repairOrder->lines->isNotEmpty();
        }

        return $repairOrder->lines()->exists();
    }
}
