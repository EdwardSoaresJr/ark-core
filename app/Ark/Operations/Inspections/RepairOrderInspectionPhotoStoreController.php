<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderInspectionPhotoStoreController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        EnsureInspectionAction $ensureInspection,
        InspectionEvidenceStore $evidenceStore,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $item = $this->itemForRepairOrder($repairOrder, $item);
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'photo' => InspectionEvidenceStore::uploadRules(required: true),
            'purpose' => ['required', Rule::enum(InspectionPhotoPurpose::class)],
        ]);

        $evidenceStore->store(
            $repairOrder,
            $item,
            $data['photo'],
            $request->user(),
            InspectionPhotoPurpose::from($data['purpose']),
        );

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Evidence uploaded.');
    }
}
