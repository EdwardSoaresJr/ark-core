<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\ValidatesInspectionScope;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MobileInspectionPhotoShowController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItemPhoto $photo,
        MobileStaffAccess $access,
    ): StreamedResponse {
        abort_unless($access->canViewRepairOrder($request->user(), $repairOrder), 403);

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
