<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DocumentStore
{
    public const MAX_KILOBYTES = 25600; // 25 MB

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
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
     * @return list<string|array<int, string>>
     */
    public static function pageUploadRules(bool $required = false): array
    {
        $rules = [
            'file',
            'mimetypes:image/jpeg,image/png,image/heic,image/heif',
            'max:'.self::MAX_KILOBYTES,
        ];

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['nullable'], $rules);
    }

    /**
     * @return array{storage_path: string, content_type: string, original_name: string|null, byte_size: int, page_count: int|null}
     */
    public function storeUploadedFile(int $customerId, UploadedFile $file): array
    {
        $contentType = strtolower((string) ($file->getMimeType() ?? ''));

        if (! in_array($contentType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Document type is not supported. Use PDF, JPG, PNG, or HEIC.');
        }

        $extension = $file->getClientOriginalExtension() ?: $this->defaultExtension($contentType);
        $path = $file->storeAs(
            'documents/'.$customerId,
            Str::uuid()->toString().'.'.strtolower($extension),
            'local',
        );

        return [
            'storage_path' => $path,
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'original_name' => $file->getClientOriginalName(),
            'byte_size' => (int) $file->getSize(),
            'page_count' => $contentType === 'application/pdf' ? null : 1,
        ];
    }

    /**
     * @return array{storage_path: string, content_type: string, original_name: string, byte_size: int, page_count: int}
     */
    public function storeAssembledPdf(int $customerId, string $absolutePdfPath, string $originalName, int $pageCount): array
    {
        return $this->storeAbsoluteFile(
            $customerId,
            $absolutePdfPath,
            'pdf',
            'application/pdf',
            $originalName,
            $pageCount,
        );
    }

    /**
     * Write new storage object (rotation / rewrite). Never overwrite existing storage_path in place.
     *
     * @return array{storage_path: string, content_type: string, original_name: string|null, byte_size: int, page_count: int|null}
     */
    public function storeAbsoluteFile(
        int $customerId,
        string $absolutePath,
        string $extension,
        string $contentType,
        ?string $originalName,
        ?int $pageCount,
    ): array {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException('Document file is missing.');
        }

        $relative = 'documents/'.$customerId.'/'.Str::uuid()->toString().'.'.strtolower($extension);
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException('Unable to read document file.');
        }

        try {
            Storage::disk('local')->put($relative, $stream);
        } finally {
            fclose($stream);
        }

        return [
            'storage_path' => $relative,
            'content_type' => $contentType,
            'original_name' => $originalName,
            'byte_size' => (int) filesize($absolutePath),
            'page_count' => $pageCount,
        ];
    }

    private function defaultExtension(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/heic', 'image/heif' => 'heic',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };
    }
}
