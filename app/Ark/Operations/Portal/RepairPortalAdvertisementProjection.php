<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Evidence\Evidence;
use App\Ark\Operations\Evidence\EvidenceType;
use App\Ark\Operations\Evidence\EvidenceVisibility;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Disposable packaging of the durable Repair Portal doorway for customer documents.
 * Documents consume this — they never mint competing access.
 */
final class RepairPortalAdvertisementProjection
{
    public function __construct(
        private readonly CreateOrReuseRepairOrderPortalAccessAction $accesses,
    ) {}

    /**
     * @return array{
     *     public_code: string,
     *     url: string,
     *     qr_data_uri: string|null,
     *     headline: string,
     *     cta: string,
     *     bullets: list<string>,
     *     has_shared_evidence: bool,
     *     photo_count: int,
     *     video_count: int
     * }
     */
    public function forRepairOrder(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $access = $this->accesses->execute($repairOrder, $actor);
        $url = route('portal.repair.show', ['code' => $access->public_code], absolute: true);

        $photoCount = Evidence::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('deleted_at')
            ->where('visibility', EvidenceVisibility::Shared->value)
            ->where('type', EvidenceType::Photo->value)
            ->count();

        $videoCount = Evidence::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('deleted_at')
            ->where('visibility', EvidenceVisibility::Shared->value)
            ->where('type', EvidenceType::Video->value)
            ->count();

        $hasShared = ($photoCount + $videoCount) > 0;
        $hasInspection = InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0;

        $bullets = ($hasShared || $hasInspection)
            ? array_values(array_filter([
                $photoCount > 0 ? $photoCount.' '.($photoCount === 1 ? 'Photo' : 'Photos') : null,
                $videoCount > 0 ? $videoCount.' '.($videoCount === 1 ? 'Video' : 'Videos') : null,
                $hasInspection ? 'Inspection results' : null,
                'Live estimate updates',
            ]))
            : [
                'Track this repair',
                'Approve work',
                'View invoices',
            ];

        return [
            'public_code' => $access->public_code,
            'url' => $url,
            'qr_data_uri' => CustomerReportQrCode::svgDataUri($url, 96),
            'headline' => 'Vehicle Portal',
            'cta' => 'View your vehicle online',
            'bullets' => $bullets,
            'has_shared_evidence' => $hasShared,
            'photo_count' => $photoCount,
            'video_count' => $videoCount,
        ];
    }
}
