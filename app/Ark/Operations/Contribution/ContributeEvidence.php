<?php

namespace App\Ark\Operations\Contribution;

use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contribution capability - human says evidence belongs in public.
 * Today's projection: Featured Media on a common-problem page.
 */
final class ContributeEvidence
{
    /**
     * @return array{id: string, path: string, alt: string, caption: string, source_repair_order_id: int, source_media_id: int, contributed_by: int, contributed_at: string}
     */
    public function contributeInspectionPhoto(
        RepairOrder $repairOrder,
        InspectionItemPhoto $photo,
        string $commonProblemSlug,
        User $contributor,
        ?string $caption = null,
    ): array {
        $slug = trim($commonProblemSlug);

        if ($slug === '' || CommonProblemRegistry::find($slug) === null) {
            throw ValidationException::withMessages([
                'common_problem_slug' => 'Choose a website page for this evidence.',
            ]);
        }

        if (! $photo->isImage()) {
            throw ValidationException::withMessages([
                'inspection_photo_id' => 'Only inspection photos can be contributed (not video).',
            ]);
        }

        if ($photo->storage_path === '' || ! Storage::disk('local')->exists($photo->storage_path)) {
            throw ValidationException::withMessages([
                'inspection_photo_id' => 'Inspection photo not found.',
            ]);
        }

        $absolutePath = Storage::disk('local')->path($photo->storage_path);

        if (! is_readable($absolutePath)) {
            throw ValidationException::withMessages([
                'inspection_photo_id' => 'Inspection photo not found.',
            ]);
        }

        $upload = new UploadedFile(
            $absolutePath,
            $photo->original_name ?: 'inspection-photo.jpg',
            $photo->content_type ?: 'image/jpeg',
            null,
            true,
        );

        $path = CommonProblemFeaturedMedia::storeUpload($slug, $upload);
        $item = $photo->item;
        $label = trim((string) ($item?->label ?? 'Inspection'));
        $purpose = $photo->purposeLabel();
        $alt = trim($label.' - '.$purpose.' inspection evidence at the shop');

        if ($altError = CommonProblemFeaturedMedia::altTextError($alt)) {
            $alt = 'Technician documenting '.$label.' during vehicle inspection at the shop';
            if (CommonProblemFeaturedMedia::altTextError($alt) !== null) {
                throw ValidationException::withMessages([
                    'inspection_photo_id' => $altError,
                ]);
            }
        }

        $captionText = trim((string) ($caption ?? ''));
        if ($captionText === '') {
            $captionText = $purpose !== '' ? $purpose : '';
        }

        $contributedAt = now()->toIso8601String();
        $entry = [
            'id' => Str::uuid()->toString(),
            'path' => $path,
            'alt' => $alt,
            'caption' => $captionText,
            'source_repair_order_id' => (int) $repairOrder->id,
            'source_media_id' => (int) $photo->id,
            'contributed_by' => (int) $contributor->id,
            'contributed_at' => $contributedAt,
        ];

        $gallery = CommonProblemFeaturedMedia::forSlug($slug);
        $gallery[] = $entry;
        CommonProblemFeaturedMedia::persistGalleryForSlug($slug, $gallery);

        return $entry;
    }
}
