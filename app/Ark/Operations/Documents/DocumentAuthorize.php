<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Facades\Storage;

final class DocumentAuthorize
{
    public function assertBelongsToCustomer(Customer $customer, Document $document): void
    {
        abort_unless((int) $document->customer_id === (int) $customer->id, 404);
        abort_unless($document->isActive(), 404);
    }

    public function assertReadableOnRepairOrder(RepairOrder $repairOrder, Document $document): void
    {
        abort_unless((int) $document->customer_id === (int) $repairOrder->customer_id, 404);
        abort_unless($document->isActive(), 404);
        abort_unless((int) ($document->repair_order_id ?? 0) === (int) $repairOrder->id, 404);
    }

    public function assertStoragePresent(Document $document): void
    {
        abort_unless(
            $document->storage_path !== ''
            && Storage::disk('local')->exists($document->storage_path),
            404,
        );
    }
}
