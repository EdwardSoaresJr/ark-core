<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreDocumentAction
{
    public function __construct(
        private readonly DocumentStore $store,
        private readonly RecordDocumentEventAction $events,
    ) {}

    public function handle(
        Customer $customer,
        UploadedFile $file,
        User $actor,
        DocumentType $type,
        string $title,
        ?string $description = null,
        ?RepairOrder $repairOrder = null,
        DocumentSource $source = DocumentSource::Upload,
        ?\DateTimeInterface $capturedAt = null,
    ): Document {
        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Document name is required.',
            ]);
        }

        if (mb_strlen($title) > 255) {
            throw ValidationException::withMessages([
                'title' => 'Document name may be at most 255 characters.',
            ]);
        }

        $description = $description !== null ? trim($description) : null;
        if ($description === '') {
            $description = null;
        }

        if ($repairOrder !== null) {
            $this->assertRepairOrderBelongsToCustomer($customer, $repairOrder);
        }

        return DB::transaction(function () use (
            $customer,
            $file,
            $actor,
            $type,
            $title,
            $description,
            $repairOrder,
            $source,
            $capturedAt,
        ): Document {
            $stored = $this->store->storeUploadedFile((int) $customer->id, $file);

            $document = Document::query()->create([
                'customer_id' => $customer->id,
                'repair_order_id' => $repairOrder?->id,
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'storage_path' => $stored['storage_path'],
                'content_type' => $stored['content_type'],
                'original_name' => $stored['original_name'],
                'byte_size' => $stored['byte_size'],
                'page_count' => $stored['page_count'],
                'source' => $source,
                'uploaded_by_user_id' => $actor->id,
                'captured_at' => $capturedAt ?? now(),
                'customer_visible' => false,
            ]);

            $eventType = match ($source) {
                DocumentSource::Scan => DocumentEventType::Scanned,
                DocumentSource::Generated => DocumentEventType::Generated,
                default => DocumentEventType::Uploaded,
            };

            $this->events->handle($document, $eventType, $actor);

            if ($repairOrder !== null) {
                $this->events->handle($document, DocumentEventType::Attached, $actor, [
                    'repair_order_id' => $repairOrder->id,
                ]);
            }

            return $document;
        });
    }

    public function assertRepairOrderBelongsToCustomer(Customer $customer, RepairOrder $repairOrder): void
    {
        if ((int) $repairOrder->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'repair_order_id' => 'Repair order must belong to this customer.',
            ]);
        }
    }
}
