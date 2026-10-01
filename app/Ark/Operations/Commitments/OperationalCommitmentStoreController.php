<?php

namespace App\Ark\Operations\Commitments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class OperationalCommitmentStoreController
{
    public function __invoke(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(CommitmentType::class)],
            'reason' => ['required', 'string', 'max:500'],
            'due_at' => ['required', 'date'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ownerIds = app(CommitmentAssignableOwners::class)
            ->all()
            ->pluck('id')
            ->all();

        abort_unless(in_array((int) $data['owner_user_id'], $ownerIds, true), 422);

        $dueAt = Carbon::parse($data['due_at'], ShopDisplayTimezone::resolve())->utc();

        OperationalCommitment::query()->create([
            'repair_order_id' => $repairOrder->id,
            'owner_user_id' => (int) $data['owner_user_id'],
            'created_by' => $request->user()?->id,
            'type' => $data['type'],
            'status' => CommitmentStatus::Open,
            'reason' => trim($data['reason']),
            'due_at' => $dueAt,
        ]);

        return redirect()
            ->back()
            ->with('status', 'Commitment recorded for RO #'.$repairOrder->repair_order_id.'.');
    }
}
