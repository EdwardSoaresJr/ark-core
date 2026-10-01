<?php

namespace App\Ark\Platform\Communications;

final class PlatformMessageMedia
{
    /**
     * @return list<array{public_id: string, kind: string, url: ?string, label: string, content_type: string}>
     */
    public static function present(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $presented = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $disposition = (string) ($attachment['disposition'] ?? '');
            if (! in_array($disposition, ['stored', 'unsupported', 'unavailable'], true)) {
                $disposition = 'unavailable';
            }

            $publicId = (string) ($attachment['public_id'] ?? '');
            $type = strtolower((string) ($attachment['content_type'] ?? ''));
            $stored = $disposition === 'stored' && self::isPublicId($publicId);
            $image = $stored && str_starts_with($type, 'image/');
            $kind = match (true) {
                $image => 'image',
                $stored => 'file',
                $disposition === 'unsupported' => 'unsupported',
                default => 'unavailable',
            };

            $presented[] = [
                'public_id' => $stored ? $publicId : '',
                'kind' => $kind,
                'url' => $stored
                    ? route('operations.communications.attachments.show', ['attachment' => $publicId])
                    : null,
                'label' => match ($kind) {
                    'image' => 'Photo',
                    'file' => self::fileLabel($type),
                    'unsupported' => 'Unsupported attachment',
                    default => 'Attachment unavailable',
                },
                'content_type' => $stored ? $type : '',
            ];
        }

        return $presented;
    }

    private static function fileLabel(string $type): string
    {
        if (str_starts_with($type, 'video/')) {
            return 'Video';
        }

        if ($type === 'application/pdf') {
            return 'PDF';
        }

        return 'Attachment';
    }

    private static function isPublicId(string $publicId): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1;
    }
}
