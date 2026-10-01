<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sole writer of is_primary. Transactional: same RO, clear prior, set new, reject retired.
 */
final class SetPrimaryEvidenceAction
{
    public function __construct(
        private readonly EvidenceAttachable $attachables,
    ) {}

    public function handle(RepairOrder $repairOrder, EvidenceAttachment $attachment, User $actor): EvidenceAttachment
    {
        return DB::transaction(function () use ($repairOrder, $attachment): EvidenceAttachment {
            $attachment = EvidenceAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
            $evidence = Evidence::query()->withTrashed()->lockForUpdate()->findOrFail($attachment->evidence_id);

            if ((int) $evidence->repair_order_id !== (int) $repairOrder->id) {
                throw ValidationException::withMessages([
                    'evidence' => 'Evidence must belong to this repair order.',
                ]);
            }

            if ($evidence->trashed() || ! $evidence->isActive()) {
                throw ValidationException::withMessages([
                    'evidence' => 'Retired evidence cannot be primary.',
                ]);
            }

            $attachable = $attachment->attachable;
            if ($attachable === null) {
                throw ValidationException::withMessages([
                    'evidence' => 'Attachable is missing.',
                ]);
            }

            $this->attachables->assertSameRepairOrder($repairOrder, $attachable);

            EvidenceAttachment::query()
                ->where('attachable_type', $attachment->attachable_type)
                ->where('attachable_id', $attachment->attachable_id)
                ->where('is_primary', true)
                ->where('id', '!=', $attachment->id)
                ->update(['is_primary' => false]);

            $attachment->update(['is_primary' => true]);

            return $attachment->fresh() ?? $attachment;
        });
    }
}
