<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Request;

final class PortalObservationRecorder
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
        private readonly Request $request,
    ) {}

    /**
     * @param  array{
     *     active_visit: ?array{repair_order_id: int},
     *     documents: array{total_count: int},
     * }  $detail
     */
    public function vehicleViewed(Customer $customer, Vehicle $vehicle, array $detail): void
    {
        $this->record(
            OperationalEventName::PortalVehicleViewed,
            $vehicle,
            $customer,
            $vehicle,
            [
                'document_count' => (int) ($detail['documents']['total_count'] ?? 0),
                'has_active_visit' => ($detail['active_visit'] ?? null) !== null,
            ],
        );

        $activeVisit = $detail['active_visit'] ?? null;

        if (is_array($activeVisit)) {
            $this->record(
                OperationalEventName::PortalActiveVisitViewed,
                $vehicle,
                $customer,
                $vehicle,
                [
                    'repair_order_id' => (int) ($activeVisit['repair_order_id'] ?? 0),
                ],
            );
        }
    }

    public function documentViewed(Customer $customer, Vehicle $vehicle, EstimateDocument $document): void
    {
        $this->recordDocumentAccess(
            OperationalEventName::PortalDocumentViewed,
            $customer,
            $vehicle,
            $document,
        );
    }

    public function documentDownloaded(Customer $customer, Vehicle $vehicle, EstimateDocument $document): void
    {
        $this->recordDocumentAccess(
            OperationalEventName::PortalDocumentDownloaded,
            $customer,
            $vehicle,
            $document,
        );
    }

    private function recordDocumentAccess(
        OperationalEventName $eventName,
        Customer $customer,
        Vehicle $vehicle,
        EstimateDocument $document,
    ): void {
        $this->record(
            $eventName,
            $document,
            $customer,
            $vehicle,
            [
                'repair_order_id' => $document->repair_order_id,
                'document_type' => $document->document_type?->value,
                'document_number' => $document->document_number,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(
        OperationalEventName $eventName,
        EstimateDocument|Vehicle $aggregate,
        Customer $customer,
        Vehicle $vehicle,
        array $payload = [],
    ): void {
        if ($this->request->session()->has(RepairOrderPortalSessionController::STAFF_ACTOR_SESSION_KEY)) {
            return;
        }

        $this->events->record(
            $eventName,
            $aggregate,
            payload: [
                ...$payload,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'portal_session_id' => PortalObservationSession::id($this->request),
            ],
        );
    }
}
