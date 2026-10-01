<?php

namespace App\Ark\Operations\Flow;

use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;

enum FlowStageKey: string
{
    case WorkArrives = 'work_arrives';
    case NeedsDiagnosis = 'needs_diagnosis';
    case BuildingEstimate = 'building_estimate';
    case WaitingApproval = 'waiting_approval';
    case WaitingParts = 'waiting_parts';
    case InRepair = 'in_repair';
    case QualityCheck = 'quality_check';
    case ReadyPickup = 'ready_pickup';

    public function label(): string
    {
        return match ($this) {
            self::WorkArrives => 'Work Arrives',
            self::NeedsDiagnosis => 'Needs Diagnosis',
            self::BuildingEstimate => 'Building Estimate',
            self::WaitingApproval => 'Waiting Approval',
            self::WaitingParts => 'Waiting Parts',
            self::InRepair => 'In Repair',
            self::QualityCheck => 'Quality Check',
            self::ReadyPickup => 'Ready Pickup',
        };
    }

    public function inventoryUrl(): string
    {
        return match ($this) {
            self::WorkArrives => route('operations.index'),
            self::InRepair => WorkboardSwimlaneCatalog::inventoryUrlForLane('shop_floor')
                ?? route('operations.workboard', ['queue' => 'shop_floor']),
            default => WorkboardSwimlaneCatalog::inventoryUrlForLane($this->workboardLaneKey())
                ?? route('operations.workboard'),
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::WorkArrives,
            self::NeedsDiagnosis,
            self::BuildingEstimate,
            self::WaitingApproval,
            self::WaitingParts,
            self::InRepair,
            self::QualityCheck,
            self::ReadyPickup,
        ];
    }

    private function workboardLaneKey(): string
    {
        return match ($this) {
            self::NeedsDiagnosis => 'needs_diagnosis',
            self::BuildingEstimate => 'building_estimate',
            self::WaitingApproval => 'waiting_approval',
            self::WaitingParts => 'waiting_parts',
            self::QualityCheck => 'quality_check',
            self::ReadyPickup => 'ready_pickup',
            default => $this->value,
        };
    }
}
