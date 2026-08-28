<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Clears the template walk so the shop can redo DVI.
 * Does not delete Other Findings (ad-hoc items without a template point).
 */
final class ResetInspectionWalkAction
{
    public static function canReset(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ]);
    }

    public function execute(RepairOrder $repairOrder, Inspection $inspection, User $actor): void
    {
        abort_unless(self::canReset($actor), 403);

        $repairOrder->ensureOpenForEditing();

        DB::transaction(function () use ($inspection): void {
            $checklistItems = $inspection->items()
                ->whereNotNull('inspection_template_item_id')
                ->with(['photos', 'measurements'])
                ->get();

            foreach ($checklistItems as $item) {
                foreach ($item->photos as $photo) {
                    if ($photo->storage_path !== '' && Storage::disk('local')->exists($photo->storage_path)) {
                        Storage::disk('local')->delete($photo->storage_path);
                    }

                    $photo->delete();
                }

                $item->measurements()->delete();

                $item->forceFill([
                    'observed_state' => InspectionObservedState::NotChecked->value,
                    'notes' => null,
                ])->save();
            }

            $inspection->forceFill([
                'completed_at' => null,
                'recorded_by_user_id' => null,
            ])->save();
        });
    }
}
