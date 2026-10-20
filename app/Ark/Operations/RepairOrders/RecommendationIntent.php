<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Work type for a concern. This is not customer urgency.
 * Priority lives on RecommendationPriority.
 */
enum RecommendationIntent: string
{
    case Maintenance = 'maintenance';
    case Diagnostic = 'diagnostic';
    case RepairVerification = 'repair_verification';

    public function staffLabel(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::Diagnostic => 'Diagnostic',
            self::RepairVerification => 'Repair Verification',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Maintenance => 'Planned upkeep - e.g. fluid service, timing belt interval, manufacturer maintenance item.',
            self::Diagnostic => 'More testing needed before repair is recommended - e.g. check engine light with unknown root cause.',
            self::RepairVerification => 'Confirm completed work - e.g. recheck after repair, drive cycle, post-repair monitor.',
        };
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorMisuseOverviewItems(): array
    {
        return [
            [
                'label' => 'Not for part source',
                'detail' => 'Customer-supplied vs shop-supplied parts belong on the part line - not priority or work type.',
            ],
            [
                'label' => 'Not for OEM / aftermarket / performance',
                'detail' => 'Part type belongs on the part line. A brake job can be Must Do regardless of pad brand.',
            ],
            [
                'label' => 'Not for warranty posture',
                'detail' => 'Scope billing and part warranty impact are separate authorities.',
            ],
            [
                'label' => 'Not inspection condition',
                'detail' => 'Pass, Monitor, Needs Attention, and Fail stay on the inspection. They do not set priority.',
            ],
        ];
    }

    public static function trySemantic(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public static function defaultSemanticForRepairOrder(RepairOrder $repairOrder): ?self
    {
        if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
            return self::Diagnostic;
        }

        return self::trySemantic(
            \App\Ark\Operations\Settings\ShopSettings::current()->default_recommendation_intent,
        );
    }
}
