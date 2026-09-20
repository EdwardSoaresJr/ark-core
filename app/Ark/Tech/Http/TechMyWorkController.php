<?php

namespace App\Ark\Tech\Http;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Tech\TechStaffGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TechMyWorkController
{
    public function __invoke(Request $request, TechStaffGate $gate): JsonResponse
    {
        abort_unless($gate->canUseTech($request->user()), 403);

        $user = $request->user();

        $orders = RepairOrder::query()
            ->with(['vehicle:id,year,make,model', 'assignedTechnician:id,name', 'inspection.items'])
            ->where('assigned_technician_id', $user->id)
            ->whereIn('status', RepairOrderStatus::techDviQueueValues())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $items = $orders->map(function (RepairOrder $repairOrder): array {
            $inspection = $repairOrder->inspection;
            $total = $inspection?->items?->count() ?? 0;
            $checked = $inspection?->items
                ?->filter(fn ($item): bool => $item->observed_state?->value !== 'not_checked')
                ->count() ?? 0;

            return [
                'id' => $repairOrder->repair_order_id,
                'repair_order_id' => $repairOrder->repair_order_id,
                'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                'status' => $repairOrder->status->value,
                'status_label' => $repairOrder->status->label(),
                'concern_summary' => $repairOrder->concern_summary,
                'next_action' => 'Continue DVI',
                'age_label' => $repairOrder->updated_at?->diffForHumans(),
                'assigned_technician' => $repairOrder->assignedTechnician?->name,
                'dvi_progress' => $total > 0 ? $checked.'/'.$total : null,
            ];
        })->values()->all();

        $emptyMessage = 'Nothing assigned to '.$user->name.'. Assign this RO to your user in ARK, or sign in as the bay technician.';

        return response()->json([
            'product' => 'ark_tech',
            'technician' => $user->name,
            'scope' => 'assigned',
            'items' => $items,
            'count' => count($items),
            'empty_message' => $items === [] ? $emptyMessage : null,
        ]);
    }
}
