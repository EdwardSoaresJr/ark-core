<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff media stream — authorization only; never records customer viewed. */
final class RepairOrderEvidenceShowController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Evidence $evidence,
    ): StreamedResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersView->value), 403);
        abort_unless((int) $evidence->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($evidence->isActive(), 404);
        abort_unless($evidence->storage_path !== '' && Storage::disk('local')->exists($evidence->storage_path), 404);

        return Storage::disk('local')->response(
            $evidence->storage_path,
            $evidence->original_name ?? 'evidence',
            ['Content-Type' => $evidence->content_type],
        );
    }
}
