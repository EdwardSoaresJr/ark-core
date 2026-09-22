<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;

final class RecordApprovedWorkScopeAction
{
    public function execute(RepairOrderConcern $concern, ?User $actor = null): ?ApprovedWorkScope
    {
        if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
            return null;
        }

        $concern->load('lines');

        $lineIds = $concern->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() || $line->type->isLabor())
            ->map(fn (RepairOrderLine $line): int => (int) $line->id)
            ->values()
            ->all();

        return ApprovedWorkScope::query()->create([
            'repair_order_id' => $concern->repair_order_id,
            'repair_order_concern_id' => $concern->id,
            'line_ids' => $lineIds,
            'recorded_by_user_id' => $actor?->id,
        ]);
    }
}
