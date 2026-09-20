<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfHttpResponse;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

final class PortalVehicleDocumentController
{
    public function __construct(
        private readonly PortalVehicleDocumentAccess $access,
        private readonly PortalObservationRecorder $observation,
    ) {}

    public function view(Vehicle $vehicle, EstimateDocument $document): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();

        $document = $this->access->resolve($vehicle, $customer, $document);
        $this->observation->documentViewed($customer, $vehicle, $document);

        abort_unless(filled($document->pdf_path), 404);
        abort_unless(Storage::disk('local')->exists($document->pdf_path), 404);

        $contents = Storage::disk('local')->get($document->pdf_path);

        return DocumentPdfHttpResponse::inline(
            $contents,
            $this->filename($document->repairOrder, $document),
        );
    }

    public function download(Vehicle $vehicle, EstimateDocument $document): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();

        $document = $this->access->resolve($vehicle, $customer, $document);
        $this->observation->documentDownloaded($customer, $vehicle, $document);

        abort_unless(filled($document->pdf_path), 404);
        abort_unless(Storage::disk('local')->exists($document->pdf_path), 404);

        $contents = Storage::disk('local')->get($document->pdf_path);

        return DocumentPdfHttpResponse::attachment(
            $contents,
            $this->filename($document->repairOrder, $document),
        );
    }

    private function filename(RepairOrder $repairOrder, EstimateDocument $document): string
    {
        if ($document->isInvoice()) {
            return sprintf('invoice-ro-%d.pdf', $repairOrder->repair_order_id);
        }

        return sprintf('estimate-ro-%d.pdf', $repairOrder->repair_order_id);
    }
}
