<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreInspectionFindingAction
{
    use ValidatesInspectionScope;

    public function __construct(
        private readonly InspectionEvidenceStore $evidenceStore,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        Inspection $inspection,
        ?User $actor,
        InspectionFindingIntent $intent,
        string $label,
        ?string $measurementValue = null,
        ?string $measurementUnit = null,
        ?string $measurementName = null,
        ?string $notes = null,
        ?int $concernId = null,
        ?UploadedFile $photo = null,
    ): InspectionItem {
        $this->assertNoTemplatePointCollision($inspection, $label);

        return DB::transaction(function () use (
            $repairOrder,
            $inspection,
            $actor,
            $intent,
            $label,
            $measurementValue,
            $measurementUnit,
            $measurementName,
            $notes,
            $concernId,
            $photo,
        ): InspectionItem {
            $hasMeasurement = filled($measurementValue);

            $observedState = $hasMeasurement
                ? InspectionObservedState::Measure
                : $intent->defaultObservedState();

            $storedNotes = $this->composeNotes($intent, $notes);

            $nextPosition = (int) $inspection->items()->max('position') + 1;

            $item = $inspection->items()->create([
                'category' => InspectionCategoryInference::fromLabel($label)->value,
                'label' => $label,
                'observed_state' => $observedState->value,
                'notes' => $storedNotes,
                'repair_order_concern_id' => $this->resolveConcernIdForRepairOrder($repairOrder, $concernId),
                'position' => $nextPosition,
            ]);

            if ($hasMeasurement) {
                $item->measurements()->create([
                    'name' => filled($measurementName) ? $measurementName : 'Reading',
                    'value' => (string) $measurementValue,
                    'unit' => filled($measurementUnit) ? $measurementUnit : null,
                    'position' => 0,
                ]);
            }

            if ($photo instanceof UploadedFile) {
                $this->evidenceStore->store(
                    $repairOrder,
                    $item,
                    $photo,
                    $actor,
                );
            }

            $this->touchInspectionRecorded($inspection, $actor);

            return $item->fresh(['measurements', 'photos', 'concern']);
        });
    }

    private function composeNotes(InspectionFindingIntent $intent, ?string $notes): ?string
    {
        $trimmed = is_string($notes) ? trim($notes) : '';

        if ($trimmed === '') {
            return $intent->notesPrefix();
        }

        return $intent->notesPrefix().' '.$trimmed;
    }

    /**
     * Other Findings are for vocabulary gaps only — never a second condition for a checklist point.
     */
    private function assertNoTemplatePointCollision(Inspection $inspection, string $label): void
    {
        $collision = InspectionFindingLabelCollision::collidingVisibleTemplatePoint($inspection, $label);
        if (! $collision instanceof InspectionItem) {
            return;
        }

        $pointLabel = trim((string) $collision->label);

        throw ValidationException::withMessages([
            'label' => 'This inspection already has a checklist point for "'.$pointLabel.'". Record the observation on that point instead of Other Findings.',
        ]);
    }
}
