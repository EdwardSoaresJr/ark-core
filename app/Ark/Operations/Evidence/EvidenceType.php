<?php

namespace App\Ark\Operations\Evidence;

enum EvidenceType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Photo',
            self::Video => 'Video',
            self::Pdf => 'PDF',
        };
    }

    public static function fromContentType(string $contentType): self
    {
        if (str_starts_with($contentType, 'image/')) {
            return self::Photo;
        }

        if (str_starts_with($contentType, 'video/')) {
            return self::Video;
        }

        if ($contentType === 'application/pdf') {
            return self::Pdf;
        }

        throw new \InvalidArgumentException('Unsupported evidence content type.');
    }
}
