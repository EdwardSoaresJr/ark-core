<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\Portal\ResolveEstimateAccessTokenAction;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer media stream — Shared + token scope only.
 * Does NOT record first_customer_viewed_at (preload-safe).
 */
final class PortalEvidenceShowController
{
    public function __invoke(
        string $token,
        Evidence $evidence,
        ResolveEstimateAccessTokenAction $resolve,
    ): StreamedResponse {
        $accessToken = $resolve->execute($token, touchViewed: false);
        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();
        abort_unless((int) $evidence->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($evidence->isCustomerFacing(), 404);
        abort_unless($evidence->storage_path !== '' && Storage::disk('local')->exists($evidence->storage_path), 404);

        return Storage::disk('local')->response(
            $evidence->storage_path,
            $evidence->original_name ?? 'evidence',
            ['Content-Type' => $evidence->content_type],
        );
    }
}
