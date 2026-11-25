<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restarts the template checklist walk without destroying historical evidence.
 *
 * Active checklist points are superseded (photos/measurements retained on those rows).
 * Fresh not_checked template rows are applied. Other Findings (no template point) stay active.
 */
final class ResetInspectionWalkAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

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

        DB::transaction(function () use ($repairOrder, $inspection, $actor): void {
            $now = now();

            $superseded = $inspection->items()
                ->whereNull('superseded_at')
                ->whereNotNull('inspection_template_item_id')
                ->count();

            // Keep freeform / Other Findings active. Supersede checklist points only.
            $inspection->items()
                ->whereNull('superseded_at')
                ->whereNotNull('inspection_template_item_id')
                ->update([
                    'superseded_at' => $now,
                    'updated_at' => $now,
                ]);

            $inspection->forceFill([
                'completed_at' => null,
                'recorded_by_user_id' => null,
                'started_at' => null,
            ])->save();

            app(ApplyInspectionTemplateAction::class)->execute(
                $repairOrder->fresh(),
                $inspection->fresh(),
                actor: $actor,
            );

            // Apply stamps started_at for a new capture session; restart should leave Not Started
            // until the technician records again.
            $inspection->fresh()->forceFill([
                'started_at' => null,
                'completed_at' => null,
            ])->save();

            $this->events->record(
                OperationalEventName::InspectionWalkRestarted,
                $repairOrder,
                actor: $actor,
                payload: [
                    'inspection_id' => $inspection->id,
                    'superseded_checklist_points' => $superseded,
                    'destructive' => false,
                ],
            );
        });
    }
}
