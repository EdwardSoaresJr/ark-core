<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class InspectionEvidenceStore
{
    /** Phone-captured inspection video (matches learn walkthrough ceiling). */
    public const MAX_KILOBYTES = 102400;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
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

    public function store(
        RepairOrder $repairOrder,
        InspectionItem $item,
        UploadedFile $file,
        ?User $actor = null,
        InspectionPhotoPurpose $purpose = InspectionPhotoPurpose::Internal,
    ): InspectionItemPhoto {
        $contentType = (string) ($file->getMimeType() ?? '');

        if (! in_array($contentType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Inspection evidence type is not supported.');
        }

        $extension = $file->getClientOriginalExtension() ?: $this->defaultExtension($contentType);
        $path = $file->storeAs(
            'inspections/'.$repairOrder->id.'/items/'.$item->id,
            Str::uuid()->toString().'.'.strtolower($extension),
            'local',
        );

        return $item->photos()->create([
            'purpose' => $purpose->value,
            'storage_path' => $path,
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'original_name' => $file->getClientOriginalName(),
            'byte_size' => $file->getSize(),
            'uploaded_by_user_id' => $actor?->id,
        ]);
    }

    private function defaultExtension(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'jpg',
        };
    }
}
