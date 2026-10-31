<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;

final class RecordApprovedWorkScopeAction
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    public function execute(RepairOrderConcern $concern, ?User $actor = null): ?ApprovedWorkScope
    {
        if ($concern->disposition !== RepairOrderConcernDisposition::Approved) {
            return null;
        }

        $concern->load('lines');

        $lines = $concern->lines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() || $line->type->isLabor())
            ->values();

        return ApprovedWorkScope::query()->create([
            'repair_order_id' => $concern->repair_order_id,
            'repair_order_concern_id' => $concern->id,
            'line_ids' => $lines->map(fn (RepairOrderLine $line): int => (int) $line->id)->all(),
            'approved_lines' => $lines->map(fn (RepairOrderLine $line): array => [
                'id' => (int) $line->id,
                'description' => (string) $line->description,
                'customer_description' => (string) ($line->customer_description ?? ''),
                'subtotal_cents' => $this->authorization->approvalSubtotalCents($line),
            ])->all(),
            'recorded_by_user_id' => $actor?->id,
        ]);
    }
}
