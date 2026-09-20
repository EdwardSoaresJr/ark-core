<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EvidenceStore
{
    public const MAX_KILOBYTES = 102400;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'application/pdf',
    ];

    /**
     * @return list<string|array<int, string>>
     */
    public static function uploadRules(bool $required = false): array
    {
        $rules = [
            'file',
            'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            'max:'.self::MAX_KILOBYTES,
        ];

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['nullable'], $rules);
    }

    /**
     * @return array{storage_path: string, content_type: string, original_name: string|null, byte_size: int, type: EvidenceType}
     */
    public function storeFile(RepairOrder $repairOrder, UploadedFile $file): array
    {
        $contentType = (string) ($file->getMimeType() ?? '');

        if (! in_array($contentType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Evidence type is not supported.');
        }

        $type = EvidenceType::fromContentType($contentType);
        $extension = $file->getClientOriginalExtension() ?: $this->defaultExtension($contentType);
        $path = $file->storeAs(
            'evidence/'.$repairOrder->id,
            Str::uuid()->toString().'.'.strtolower($extension),
            'local',
        );

        return [
            'storage_path' => $path,
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'original_name' => $file->getClientOriginalName(),
            'byte_size' => (int) $file->getSize(),
            'type' => $type,
        ];
    }

    private function defaultExtension(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };
    }
}
