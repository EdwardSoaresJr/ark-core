<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

final class CustomerVehicleDetailProjection
{
    private const RECENT_VISIT_LIMIT = 5;

    public function __construct(
        private readonly CustomerFacingEstimateStatus $estimateStatus,
        private readonly EstimateDocumentService $documents,
        private readonly CreateOrReuseEstimateAccessTokenAction $estimateTokens,
    ) {}

    /**
     * @return array{
     *     vehicle: array{display_name: string, plate: ?string, vin: ?string},
     *     active_visit: ?array{
     *         repair_order_id: int,
     *         status_label: string,
     *         summary: string,
     *         opened_at_label: ?string,
     *         needs_attention: bool,
     *         primary_document_id: ?int,
     *         primary_document_label: ?string,
     *         review_url: ?string,
     *         review_label: ?string,
     *         inspection_url: ?string,
     *     },
     *     last_visit: ?array{repair_order_id: int, summary: string, occurred_at_label: string, inspection_url: ?string},
     *     recent_visits: list<array{repair_order_id: int, summary: string, occurred_at_label: string, inspection_url: ?string}>,
     *     documents: array{total_count: int, items: list<array{id: int, label: string, type_label: string, occurred_at_label: string, has_pdf: bool, view_url: string, download_url: string, review_url: ?string, review_label: ?string}>},
     *     shop: array{phone_display: string, phone_tel: string, sms_href: string},
     * }
     */
    public function forVehicle(Vehicle $vehicle, Customer $viewer): array
    {
        $this->assertViewerMayAccessVehicle($vehicle, $viewer);

        $vehicle->loadMissing([
            'repairOrders.concerns',
            'repairOrders.estimateDocuments',
        ]);

        $repairOrders = $vehicle->repairOrders
            ->sortByDesc(fn (RepairOrder $repairOrder): int => $this->visitSortTimestamp($repairOrder))
            ->values();

        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        $activeVisit = $repairOrders
            ->first(fn (RepairOrder $repairOrder): bool => in_array($repairOrder->status->value, $activeStatuses, true));

        $historicalVisits = $repairOrders
            ->reject(fn (RepairOrder $repairOrder): bool => $activeVisit !== null && $repairOrder->id === $activeVisit->id)
            ->values();

        $documents = $this->customerDocuments($repairOrders, $vehicle);

        $recentVisits = $historicalVisits
            ->take(self::RECENT_VISIT_LIMIT)
            ->map(fn (RepairOrder $repairOrder): array => $this->visitRow($repairOrder, $vehicle))
            ->all();

        $lastVisit = $recentVisits[0] ?? null;
        $activeVisitRow = $activeVisit !== null ? $this->activeVisitRow($activeVisit, $vehicle) : null;

        $shop = ShopSettings::current();
        $phoneDisplay = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';

        return [
            'vehicle' => [
                'display_name' => $vehicle->display_name,
                'plate' => filled($vehicle->plate) ? strtoupper((string) $vehicle->plate) : null,
                'vin' => filled($vehicle->vin) ? strtoupper((string) $vehicle->vin) : null,
            ],
            'active_visit' => $activeVisitRow,
            'last_visit' => $lastVisit,
            'recent_visits' => $recentVisits,
            'documents' => [
                'total_count' => count($documents),
                'items' => $documents,
            ],
            'shop' => [
                'phone_display' => $phoneDisplay,
                'phone_tel' => $phoneTel,
                'sms_href' => 'sms:'.$phoneTel,
            ],
        ];
    }

    private function assertViewerMayAccessVehicle(Vehicle $vehicle, Customer $viewer): void
    {
        if ($viewer->id !== $vehicle->customer_id) {
            throw new AuthorizationException('Customer cannot view this vehicle.');
        }
    }

    /**
     * @return array{
     *     repair_order_id: int,
     *     status_label: string,
     *     summary: string,
     *     opened_at_label: ?string,
     *     needs_attention: bool,
     *     primary_document_id: ?int,
     *     primary_document_label: ?string,
     *     review_url: ?string,
     *     review_label: ?string,
     *     inspection_url: ?string,
     * }
     */
    private function activeVisitRow(RepairOrder $repairOrder, Vehicle $vehicle): array
    {
        $statusLabel = $this->estimateStatus->labelForRepairOrder($repairOrder);
        $primaryDocument = $repairOrder->estimateDocuments
            ->filter(fn (EstimateDocument $document): bool => $document->generated_at !== null)
            ->sortByDesc(fn (EstimateDocument $document): int => $document->generated_at?->timestamp ?? 0)
            ->first();

        $needsAttention = $statusLabel === 'Awaiting your approval';
        $reviewUrl = null;
        $reviewLabel = null;

        if ($needsAttention) {
            $accessToken = $this->estimateTokens->execute($repairOrder);
            $reviewUrl = route('portal.estimates.show', ['token' => $accessToken->plainToken]);
            $reviewLabel = 'estimate';
        } elseif ($primaryDocument instanceof EstimateDocument && $this->documents->hasViewablePdf($primaryDocument)) {
            $reviewUrl = route('portal.vehicles.documents.view', [$vehicle, $primaryDocument]);
            $reviewLabel = $this->documentLabel($primaryDocument);
        }

        return [
            'repair_order_id' => (int) $repairOrder->repair_order_id,
            'status_label' => $statusLabel,
            'summary' => $this->visitSummary($repairOrder),
            'opened_at_label' => $this->formatDateLabel($repairOrder->opened_at ?? $repairOrder->created_at),
            'needs_attention' => $needsAttention,
            'primary_document_id' => $primaryDocument instanceof EstimateDocument ? (int) $primaryDocument->id : null,
            'primary_document_label' => $primaryDocument instanceof EstimateDocument ? $this->documentLabel($primaryDocument) : null,
            'review_url' => $reviewUrl,
            'review_label' => $reviewLabel,
            'inspection_url' => $this->inspectionReportUrl($repairOrder, $vehicle),
        ];
    }

    /**
     * @return array{repair_order_id: int, summary: string, occurred_at_label: string, inspection_url: ?string}
     */
    private function visitRow(RepairOrder $repairOrder, Vehicle $vehicle): array
    {
        return [
            'repair_order_id' => (int) $repairOrder->repair_order_id,
            'summary' => $this->visitSummary($repairOrder),
            'occurred_at_label' => $this->formatDateLabel($this->visitOccurredAt($repairOrder)),
            'inspection_url' => $this->inspectionReportUrl($repairOrder, $vehicle),
        ];
    }

    private function inspectionReportUrl(RepairOrder $repairOrder, Vehicle $vehicle): ?string
    {
        if (InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) < 1) {
            return null;
        }

        return route('portal.vehicles.inspections.show', [
            'vehicle' => $vehicle,
            'repairOrder' => $repairOrder,
        ]);
    }

    private function visitSummary(RepairOrder $repairOrder): string
    {
        $concern = $repairOrder->concerns
            ->sortBy('position')
            ->first();

        $summary = trim((string) ($concern?->summary ?? ''));

        if ($summary !== '') {
            return $summary;
        }

        $fallback = trim((string) ($repairOrder->concern_summary ?? ''));

        return $fallback !== '' ? $fallback : 'Service visit';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RepairOrder>  $repairOrders
     * @return list<array{id: int, label: string, type_label: string, occurred_at_label: string, has_pdf: bool, view_url: string, download_url: string, review_url: ?string, review_label: ?string}>
     */
    private function customerDocuments($repairOrders, Vehicle $vehicle): array
    {
        return $repairOrders
            ->flatMap(fn (RepairOrder $repairOrder) => $repairOrder->estimateDocuments
                ->filter(fn (EstimateDocument $document): bool => $document->generated_at !== null)
                ->map(fn (EstimateDocument $document): array => [
                    'row' => $this->documentRow($document, $repairOrder, $vehicle),
                    'sort_ts' => $document->generated_at?->timestamp ?? 0,
                ]))
            ->sortByDesc('sort_ts')
            ->pluck('row')
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, label: string, type_label: string, occurred_at_label: string, has_pdf: bool, view_url: string, download_url: string, review_url: ?string, review_label: ?string}
     */
    private function documentRow(EstimateDocument $document, RepairOrder $repairOrder, Vehicle $vehicle): array
    {
        $hasPdf = $this->documents->hasViewablePdf($document);
        $reviewUrl = null;
        $reviewLabel = null;

        if (
            $document->document_type === FinancialDocumentType::Estimate
            && $this->estimateStatus->labelForRepairOrder($repairOrder) === 'Awaiting your approval'
        ) {
            $accessToken = $this->estimateTokens->execute($repairOrder);
            $reviewUrl = route('portal.estimates.show', ['token' => $accessToken->plainToken]);
            $reviewLabel = 'Review estimate';
        }

        return [
            'id' => (int) $document->id,
            'label' => $this->documentLabel($document),
            'type_label' => $document->document_type?->label() ?? 'Document',
            'occurred_at_label' => $this->formatDateLabel($document->generated_at),
            'has_pdf' => $hasPdf,
            'view_url' => route('portal.vehicles.documents.view', [$vehicle, $document]),
            'download_url' => route('portal.vehicles.documents.download', [$vehicle, $document]),
            'review_url' => $reviewUrl,
            'review_label' => $reviewLabel,
        ];
    }

    private function documentLabel(EstimateDocument $document): string
    {
        if ($document->document_type === FinancialDocumentType::Invoice && $document->document_number) {
            return 'Invoice #'.$document->document_number;
        }

        if ($document->document_type === FinancialDocumentType::Estimate) {
            return 'Estimate';
        }

        return $document->document_type?->label() ?? 'Document';
    }

    private function visitOccurredAt(RepairOrder $repairOrder): ?Carbon
    {
        return $repairOrder->closed_at
            ?? $repairOrder->posted_at
            ?? $repairOrder->opened_at
            ?? $repairOrder->created_at;
    }

    private function visitSortTimestamp(RepairOrder $repairOrder): int
    {
        return $this->visitOccurredAt($repairOrder)?->timestamp ?? 0;
    }

    private function formatDateLabel(?Carbon $occurredAt): ?string
    {
        if ($occurredAt === null) {
            return null;
        }

        return ShopDisplayTimezone::formatDate($occurredAt);
    }
}
