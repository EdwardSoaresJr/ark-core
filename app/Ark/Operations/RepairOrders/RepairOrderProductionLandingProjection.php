<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Inspections\InspectionCoverageProjection;
use App\Models\User;

/**
 * Technician production RO control surface — orientation before the walk.
 * Does not embed the inspection checklist.
 */
final class RepairOrderProductionLandingProjection
{
    /**
     * @return array{
     *     vehicle_label: string,
     *     customer_label: string,
     *     ro_label: string,
     *     status_label: string,
     *     visit_reason: ?string,
     *     why_here: string,
     *     concerns: list<array{summary: string, disposition_label: string}>,
     *     next_action: string,
     *     coverage: array<string, mixed>,
     *     walk_url: string,
     *     tablet_url: string,
     *     capture_url: string,
     *     capture_surface: string,
     *     back_url: string,
     * }
     */
    public static function for(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'concerns.workGroups.ownerUser']);

        $coverage = InspectionCoverageProjection::for($repairOrder, $actor);
        $visitReason = filled($repairOrder->visit_reason)
            ? trim((string) $repairOrder->visit_reason)
            : null;
        $concernSummary = trim((string) ($repairOrder->concern_summary ?? ''));

        $ownedPackages = $repairOrder->concerns
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->workGroups)
            ->filter(function (RepairOrderWorkGroup $group) use ($actor): bool {
                if ($actor === null) {
                    return true;
                }

                return $group->isOwnedByUserId((int) $actor->id);
            })
            ->sortBy('position')
            ->values()
            ->map(fn (RepairOrderWorkGroup $group): array => [
                'summary' => $group->title !== '' ? $group->title : 'Repair Action',
                'disposition_label' => $group->ownerUser?->name ?? 'Unassigned',
                'status_label' => ($group->status instanceof RepairActionStatus
                    ? $group->status
                    : RepairActionStatus::Pending)->label(),
                'latest_update' => $group->latest_update,
                'updated_at' => $group->updated_at?->timezone(config('app.timezone'))->format('g:i A'),
            ])
            ->all();

        $concerns = $ownedPackages !== []
            ? $ownedPackages
            : $repairOrder->concerns
                ->sortBy('position')
                ->values()
                ->map(function (RepairOrderConcern $concern): array {
                    $summary = trim((string) $concern->summary);

                    return [
                        'summary' => $summary !== '' ? $summary : 'Concern',
                        'disposition_label' => $concern->disposition?->label() ?? 'Draft',
                    ];
                })
                ->all();

        $whyHere = $visitReason
            ?? ($concernSummary !== '' ? $concernSummary : 'Assigned production work.');

        return [
            'vehicle_label' => (string) ($repairOrder->vehicle?->display_name ?? 'Vehicle'),
            'customer_label' => (string) ($repairOrder->customer?->name ?? 'Customer'),
            'ro_label' => 'RO #'.$repairOrder->repair_order_id,
            'status_label' => $repairOrder->statusDisplayLabel(),
            'visit_reason' => $visitReason,
            'why_here' => $whyHere,
            'concerns' => $concerns,
            'owned_repair_actions' => $ownedPackages,
            'next_action' => self::nextActionLabel($coverage),
            'coverage' => $coverage,
            'walk_url' => $coverage['walk_url'],
            'tablet_url' => $coverage['tablet_url'],
            'capture_url' => $coverage['capture_url'],
            'capture_surface' => $coverage['capture_surface'],
            'back_url' => route('operations.workboard'),
        ];
    }

    /**
     * @param  array{started: bool, checked: int, total: int, cta_label: string, posture_label: string}  $coverage
     */
    private static function nextActionLabel(array $coverage): string
    {
        if (! ($coverage['can_record'] ?? false)) {
            return 'Review assigned work';
        }

        $cta = (string) $coverage['cta_label'];
        $posture = (string) $coverage['posture_label'];

        if (($coverage['started'] ?? false) || (($coverage['total'] ?? 0) > 0 && ($coverage['checked'] ?? 0) === ($coverage['total'] ?? 0))) {
            return $cta.' · '.$posture;
        }

        return $cta;
    }
}
