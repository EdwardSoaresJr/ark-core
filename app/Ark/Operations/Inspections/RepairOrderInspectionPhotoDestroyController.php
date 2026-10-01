<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RepairOrderInspectionPhotoDestroyController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItemPhoto $photo,
        EnsureInspectionAction $ensureInspection,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $photo = $this->photoForRepairOrder($repairOrder, $photo);
        $item = $photo->item;
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        if ($photo->storage_path !== '' && Storage::disk('local')->exists($photo->storage_path)) {
            Storage::disk('local')->delete($photo->storage_path);
        }

        $photo->delete();

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Photo removed.');
    }
}
