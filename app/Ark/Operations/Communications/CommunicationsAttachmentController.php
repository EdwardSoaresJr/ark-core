<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\Communications\ArkCommunicationsClient;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use Illuminate\Http\Response;

final class CommunicationsAttachmentController
{
    public function __invoke(string $attachment, ArkCommunicationsClient $client): Response
    {
        abort_unless(ManagedCommunicationsGate::platformInbox() || ManagedCommunicationsGate::platformSend(), 404);
        abort_unless(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $attachment) === 1, 404);

        $fetched = $client->fetchAttachment($attachment);
        abort_unless($fetched['ok'] ?? false, 404);

        $type = strtolower((string) ($fetched['content_type'] ?? ''));
        abort_unless($this->allowed($type), 404);

        return response((string) $fetched['bytes'], 200, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function allowed(string $type): bool
    {
        return in_array($type, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/quicktime',
            'application/pdf',
        ], true);
    }
}
