<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Vehicles\Vehicle;

final class PortalVehicleDocumentAccess
{
    public function __construct(
        private readonly EstimateDocumentService $documents,
    ) {}

    public function resolve(Vehicle $vehicle, Customer $customer, EstimateDocument $document): EstimateDocument
    {
        abort_unless($vehicle->customer_id === $customer->id, 404);

        $document->loadMissing('repairOrder');

        abort_unless($document->repairOrder !== null, 404);
        abort_unless($document->repairOrder->vehicle_id === $vehicle->id, 404);
        abort_unless($document->repairOrder->customer_id === $customer->id, 404);
        abort_unless($document->generated_at !== null, 404);
        abort_unless($this->documents->hasViewablePdf($document), 404);

        return $document;
    }
}
