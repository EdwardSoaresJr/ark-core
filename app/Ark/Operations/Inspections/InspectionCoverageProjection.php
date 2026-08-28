<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Observable inspection coverage only — not completion, not lifecycle.
 * Does not write inspections.completed_at.
 * Posture keys/labels come from InspectionPostureProjection (single derivation).
 */
final class InspectionCoverageProjection
{
    /**
     * @return array{
     *     can_record: bool,
     *     has_inspection: bool,
     *     checked: int,
     *     total: int,
     *     remaining: int,
     *     started: bool,
     *     posture_key: string,
     *     posture_label: string,
     *     posture_headline: string,
     *     posture_detail: ?string,
     *     posture_percent: ?int,
     *     attention_count: int,
     *     cta_label: string,
     *     walk_url: string,
     *     tablet_url: string,
     *     capture_surface: string,
     *     capture_url: string,
     *     companion_deep_link: string,
     *     template_name: ?string,
     *     has_captured_evidence: bool,
     *     retained_history_count: int,
     *     previous_template_name: ?string,
     * }
     */
    public static function for(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $canRecord = InspectionCaptureLinks::canRecord($actor, $repairOrder);
        $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder);
        $capture = InspectionCaptureSurfaceResolver::forRepairOrder($repairOrder);
        $inspection = Inspection::query()
            ->where('repair_order_id', $repairOrder->id)
            ->with('previousTemplate')
            ->first();

        return [
            'can_record' => $canRecord,
            'has_inspection' => $inspection instanceof Inspection,
            'checked' => $posture->checked,
            'total' => $posture->total,
            'remaining' => $posture->remaining,
            'started' => $posture->started,
            'posture_key' => $posture->key,
            'posture_label' => $posture->label(),
            'posture_headline' => $posture->headline,
            'posture_detail' => $posture->detail,
            'posture_percent' => $posture->percentComplete,
            'attention_count' => $posture->attentionCount,
            'cta_label' => self::ctaLabel($posture),
            'walk_url' => $capture['desktop_walk_url'],
            'tablet_url' => $capture['tablet_url'],
            'capture_surface' => $capture['surface'],
            'capture_url' => $capture['url'],
            'companion_deep_link' => \App\Ark\Mobile\MobileCompanionDeepLink::repairOrderInspection(
                (int) $repairOrder->repair_order_id,
            ),
            'template_name' => $posture->templateName,
            'has_captured_evidence' => $inspection instanceof Inspection && $inspection->hasCapturedEvidence(),
            'retained_history_count' => $inspection instanceof Inspection ? $inspection->retainedHistoryCount() : 0,
            'previous_template_name' => $inspection?->previousTemplate?->name,
        ];
    }

    private static function ctaLabel(InspectionPosture $posture): string
    {
        if (! $posture->started) {
            return 'Open Inspection';
        }

        if ($posture->key === InspectionPosture::COMPLETE || $posture->key === InspectionPosture::NEEDS_REVIEW) {
            return 'Open Inspection';
        }

        if ($posture->remaining > 0) {
            return 'Continue Inspection — '.$posture->remaining.' points remaining';
        }

        return 'Continue Inspection';
    }
}
