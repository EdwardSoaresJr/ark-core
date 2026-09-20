<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepairOrderInspectionPhotoShowController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItemPhoto $photo,
    ): StreamedResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersView->value), 403);

        $photo = $this->photoForRepairOrder($repairOrder, $photo);

        abort_unless($photo->storage_path !== '' && Storage::disk('local')->exists($photo->storage_path), 404);

        return Storage::disk('local')->response(
            $photo->storage_path,
            $photo->original_name ?? 'inspection-photo',
            [
                'Content-Type' => $photo->content_type,
            ],
        );
    }
}
