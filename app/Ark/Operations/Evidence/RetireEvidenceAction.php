<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-retire only. Bytes remain on disk in v1; physical purge is a later retention capability.
 */
final class RetireEvidenceAction
{
    public function handle(RepairOrder $repairOrder, Evidence $evidence): void
    {
        if ((int) $evidence->repair_order_id !== (int) $repairOrder->id) {
            throw ValidationException::withMessages([
                'evidence' => 'Evidence must belong to this repair order.',
            ]);
        }

        if (! $evidence->isActive()) {
            return;
        }

        DB::transaction(function () use ($evidence): void {
            $evidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);

            EvidenceAttachment::query()
                ->where('evidence_id', $evidence->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $evidence->delete();
        });
    }
}
