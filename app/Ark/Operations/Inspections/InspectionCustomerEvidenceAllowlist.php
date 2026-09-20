<?php

namespace App\Ark\Operations\Inspections;

/**
 * Customer reporting evidence boundary — allowlist, not blacklist.
 * Unknown / future purposes stay hidden until explicitly classified.
 */
final class InspectionCustomerEvidenceAllowlist
{
    public static function includes(mixed $purpose): bool
    {
        if ($purpose instanceof InspectionPhotoPurpose) {
            return $purpose->isCustomerFacing();
        }

        if (! is_string($purpose) || $purpose === '') {
            return false;
        }

        $resolved = InspectionPhotoPurpose::tryFrom($purpose);

        return $resolved instanceof InspectionPhotoPurpose && $resolved->isCustomerFacing();
    }

    /**
     * @param  iterable<\App\Ark\Operations\Inspections\InspectionItemPhoto>  $photos
     * @return list<InspectionItemPhoto>
     */
    public static function filterPhotos(iterable $photos): array
    {
        $allowed = [];

        foreach ($photos as $photo) {
            if (! $photo instanceof InspectionItemPhoto) {
                continue;
            }

            if (self::includes($photo->purpose)) {
                $allowed[] = $photo;
            }
        }

        return $allowed;
    }
}
