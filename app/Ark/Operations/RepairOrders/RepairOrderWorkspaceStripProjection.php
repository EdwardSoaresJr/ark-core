<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Inspections\InspectionCaptureLinks;
use App\Ark\Operations\Vehicles\VinDisplay;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Sticky identity strip for the Repair Order — identity only.
 * Next actions live on {@see RepairOrderFooterProjection}.
 */
final readonly class RepairOrderWorkspaceStripProjection
{
    public function __construct(
        public string $roLabel,
        public string $customerLabel,
        public string $vehicleLabel,
        public ?string $vin,
        public string $mode,
        public RepairOrderWorkspaceStripPrimaryAction $primaryAction,
    ) {}

    public static function for(RepairOrder $repairOrder, string $mode = 'presentation', ?User $actor = null): self
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'lines']);

        $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder, includeStaffPosture: false);
        $normalizedMode = match ($mode) {
            'inspect' => 'inspect',
            'review' => 'presentation', // retired competing surface
            'edit', 'builder' => 'presentation',
            default => 'presentation',
        };

        // Identity strip no longer owns a primary CTA — footer does.
        // Keep primaryAction as none for inspect deep-links that still pass strip.
        $primary = RepairOrderWorkspaceStripPrimaryAction::none();

        if ($normalizedMode === 'inspect' && InspectionCaptureLinks::canRecord($actor, $repairOrder)) {
            $coverage = \App\Ark\Operations\Inspections\InspectionCoverageProjection::for($repairOrder, $actor);
            $primary = new RepairOrderWorkspaceStripPrimaryAction(
                key: 'open_inspection',
                label: (string) $coverage['cta_label'],
                href: $coverage['capture_url'],
                disabled: false,
                title: 'Continue the vehicle inspection',
                opensInNewTab: true,
            );
        }

        return new self(
            roLabel: 'RO #'.$repairOrder->repair_order_id,
            customerLabel: $identity['customer']['title'],
            vehicleLabel: $identity['vehicle']['title'],
            vin: VinDisplay::normalize($repairOrder->vehicle?->authoritativeVin()),
            mode: $normalizedMode,
            primaryAction: $primary,
        );
    }
}
