<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class OutboundAttachmentStore
{
    public const MAX_BYTES = 5_242_880;

    public const STORAGE_DIRECTORY = 'mms-outbound';

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'application/pdf',
    ];

    /**
     * @return array{storage_path: string, media_token: string, public_url: string, content_type: string, byte_size: int}
     */
    public function store(UploadedFile $file): array
    {
        $contentType = (string) $file->getMimeType();

        if (! in_array($contentType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Attachment type is not supported for MMS.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Attachment exceeds the 5 MB MMS limit.');
        }

        $mediaToken = (string) Str::uuid();
        $extension = $file->guessExtension() ?: 'bin';
        $filename = $mediaToken.'.'.$extension;
        $path = self::STORAGE_DIRECTORY.'/'.$filename;

        $file->storeAs(self::STORAGE_DIRECTORY, $filename, 'local');

        abort_unless(Storage::disk('local')->exists($path), 500, 'Outbound MMS attachment could not be stored.');

        $publicUrl = URL::temporarySignedRoute(
            'messaging.outbound-media',
            now()->addDay(),
            ['token' => $mediaToken],
            absolute: true,
        );

        return [
            'storage_path' => $path,
            'media_token' => $mediaToken,
            'public_url' => $this->ensureHttps($publicUrl),
            'content_type' => $contentType,
            'byte_size' => (int) $file->getSize(),
        ];
    }

    public static function resolveStoragePath(string $mediaToken): ?string
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $mediaToken)) {
            return null;
        }

        foreach (Storage::disk('local')->files(self::STORAGE_DIRECTORY) as $path) {
            $basename = basename($path);

            if (str_starts_with($basename, $mediaToken.'.')) {
                return $path;
            }
        }

        return null;
    }

    private function ensureHttps(string $url): string
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            return (string) preg_replace('#^http://#', 'https://', $url);
        }

        return $url;
    }
}
