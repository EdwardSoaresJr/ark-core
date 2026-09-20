<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Associates an existing document with one RO. Does not copy file bytes.
 *
 * Freeze: A document exists once. Relationships determine where it appears.
 * The physical document is never duplicated merely to satisfy a relationship.
 */
final class AttachDocumentToRepairOrderAction
{
    public function __construct(
        private readonly RecordDocumentEventAction $events,
    ) {}

    public function handle(Document $document, RepairOrder $repairOrder, ?User $actor = null): Document
    {
        if (! $document->isActive()) {
            throw ValidationException::withMessages([
                'document' => 'Retired documents cannot be attached.',
            ]);
        }

        if ((int) $document->customer_id !== (int) $repairOrder->customer_id) {
            throw ValidationException::withMessages([
                'repair_order_id' => 'Repair order must belong to the same customer as this document.',
            ]);
        }

        if ((int) ($document->repair_order_id ?? 0) === (int) $repairOrder->id) {
            return $document;
        }

        return DB::transaction(function () use ($document, $repairOrder, $actor): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $locked->repair_order_id = $repairOrder->id;
            $locked->save();

            $this->events->handle($locked, DocumentEventType::Attached, $actor, [
                'repair_order_id' => $repairOrder->id,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }
}
