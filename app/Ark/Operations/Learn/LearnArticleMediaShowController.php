<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Operations\Staff\StaffFrontDoor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearnArticleMediaShowController
{
    public function __invoke(Request $request, LearnArticleMedia $media): StreamedResponse
    {
        abort_unless(StaffFrontDoor::canUseStaffShell($request->user()), 403);
        abort_unless($media->storage_path !== null && $media->storage_path !== '', 404);
        abort_unless(Storage::disk('local')->exists($media->storage_path), 404);

        return Storage::disk('local')->response(
            $media->storage_path,
            $media->original_name ?? 'learn-media',
            [
                'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            ],
        );
    }
}
