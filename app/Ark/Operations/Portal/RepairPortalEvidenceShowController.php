<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Evidence\Evidence;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared evidence stream via durable Repair Portal code.
 * Authorization only — does not record customer viewed.
 */
final class RepairPortalEvidenceShowController
{
    public function __invoke(
        string $code,
        Evidence $evidence,
        ResolveRepairOrderPortalAccessAction $resolve,
    ): StreamedResponse {
        $access = $resolve->byPublicCode($code);
        abort_unless($access !== null, 404);

        abort_unless((int) $evidence->repair_order_id === (int) $access->repair_order_id, 404);
        abort_unless($evidence->isCustomerFacing(), 404);
        abort_unless($evidence->storage_path !== '' && Storage::disk('local')->exists($evidence->storage_path), 404);

        return Storage::disk('local')->response(
            $evidence->storage_path,
            $evidence->original_name ?? 'evidence',
            ['Content-Type' => $evidence->content_type],
        );
    }
}
