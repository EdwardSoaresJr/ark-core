<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\ValidatesInspectionScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalInspectionPhotoShowController
{
    use ValidatesInspectionScope;

    public function __invoke(
        string $token,
        InspectionItemPhoto $photo,
        ResolveInspectionAccessTokenAction $resolve,
    ): StreamedResponse {
        $accessToken = $resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();
        $photo = $this->photoForRepairOrder($repairOrder, $photo);

        abort_unless(
            \App\Ark\Operations\Inspections\InspectionCustomerEvidenceAllowlist::includes($photo->purpose),
            404,
        );

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
