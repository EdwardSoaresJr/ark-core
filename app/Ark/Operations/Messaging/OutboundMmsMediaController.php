<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutboundMmsMediaController
{
    public function __invoke(string $token): StreamedResponse
    {
        $path = OutboundAttachmentStore::resolveStoragePath($token);

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        $contentType = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('local')->response(
            $path,
            headers: [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
