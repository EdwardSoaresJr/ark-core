<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Support\Collection;

/**
 * Phase 0 customer projection safety net.
 *
 * Historical freeform Other Findings can collide with a template-linked point label.
 * Prefer the template-linked point as the authoritative condition; never invent a second
 * condition card for the same defined vocabulary. Orphan customer-visible evidence from
 * the freeform row is merged onto the authoritative point in the projection only.
 */
final class InspectionReportCollidingFindingResolver
{
    /**
     * @param  Collection<int, InspectionItem>  $customerFacingItems  Walk-visible checklist (+ later extras)
     * @param  Collection<int, InspectionItem>  $extras  Recorded items not already in the walk list
     * @return array{
     *     extras: Collection<int, InspectionItem>,
     *     merges: array<int, list<InspectionItem>>,
     * }
     */
    public static function resolve(Inspection $inspection, Collection $customerFacingItems, Collection $extras): array
    {
        $customerFacingIds = $customerFacingItems->pluck('id')->all();

        /** @var array<string, InspectionItem> $authoritativeByLabel */
        $authoritativeByLabel = [];
        foreach ($customerFacingItems as $item) {
            if ($item->inspection_template_item_id === null) {
                continue;
            }
            $key = InspectionFindingLabelCollision::normalize((string) $item->label);
            if ($key === '' || isset($authoritativeByLabel[$key])) {
                continue;
            }
            $authoritativeByLabel[$key] = $item;
        }

        /** @var array<int, list<InspectionItem>> $merges */
        $merges = [];

        $keptExtras = $extras
            ->filter(function (InspectionItem $extra) use (
                $inspection,
                $authoritativeByLabel,
                $customerFacingIds,
                &$merges,
            ): bool {
                if ($extra->inspection_template_item_id !== null) {
                    return true;
                }

                $key = InspectionFindingLabelCollision::normalize((string) $extra->label);
                if ($key === '') {
                    return true;
                }

                $authority = $authoritativeByLabel[$key]
                    ?? InspectionFindingLabelCollision::collidingTemplatePoint($inspection, (string) $extra->label);

                if (! $authority instanceof InspectionItem) {
                    return true;
                }

                // Authority exists but is not on the customer-facing walk (e.g. hidden rear path).
                // Keep the freeform so evidence is not buried on an invisible point.
                if (! in_array($authority->id, $customerFacingIds, true)) {
                    return true;
                }

                $merges[$authority->id][] = $extra;

                return false;
            })
            ->values();

        return [
            'extras' => $keptExtras,
            'merges' => $merges,
        ];
    }

    /**
     * Projection-only merge: never changes stored condition on the authoritative point.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<InspectionItem>  $freeforms
     * @param  callable(InspectionItemPhoto): ?string|null  $photoUrlResolver
     * @return array<string, mixed>
     */
    public static function mergeEvidenceIntoPayload(
        array $payload,
        InspectionItem $authoritative,
        array $freeforms,
        ?callable $photoUrlResolver,
        bool $embedImageDataUris,
        callable $imageDataUri,
    ): array {
        // $authoritative condition/state is already in $payload — never overwritten here.

        foreach ($freeforms as $freeform) {
            $freeform->loadMissing(['measurements', 'photos']);

            $freeformNote = InspectionFindingIntent::stripNotesPrefix($freeform->notes);
            if (filled($freeformNote)) {
                $existing = trim((string) ($payload['note'] ?? ''));
                if ($existing === '') {
                    $payload['note'] = $freeformNote;
                } elseif (! str_contains(mb_strtolower($existing), mb_strtolower($freeformNote))) {
                    $payload['note'] = $existing."\n\n".$freeformNote;
                }
            }

            foreach ($freeform->measurements as $measurement) {
                if (! filled($measurement->value)) {
                    continue;
                }

                $name = (string) $measurement->name;
                $already = collect($payload['measurements'] ?? [])->contains(
                    fn (array $row): bool => strcasecmp((string) ($row['name'] ?? ''), $name) === 0
                );

                if ($already) {
                    $formatted = $measurement->formattedValue();
                    $line = 'Also recorded: '.$name.' '.$formatted;
                    $existing = trim((string) ($payload['note'] ?? ''));
                    if ($existing === '') {
                        $payload['note'] = $line;
                    } elseif (! str_contains(mb_strtolower($existing), mb_strtolower($formatted))) {
                        $payload['note'] = $existing."\n\n".$line;
                    }

                    continue;
                }

                $payload['measurements'][] = [
                    'key' => 'merged_'.$measurement->id,
                    'name' => $name,
                    'value' => (string) $measurement->value,
                    'unit' => $measurement->unit,
                    'formatted' => $measurement->formattedValue(),
                    'type' => 'number',
                ];
            }

            foreach (InspectionCustomerEvidenceAllowlist::filterPhotos($freeform->photos) as $photo) {
                $existingIds = collect($payload['photos'] ?? [])
                    ->merge($payload['videos'] ?? [])
                    ->pluck('id')
                    ->all();
                if (in_array($photo->id, $existingIds, true)) {
                    continue;
                }

                $url = $photoUrlResolver ? $photoUrlResolver($photo) : null;
                if ($embedImageDataUris && $photo->isImage()) {
                    $url = $imageDataUri($photo) ?? $url;
                }

                $entry = [
                    'id' => $photo->id,
                    'url' => $url,
                    'is_image' => $photo->isImage(),
                    'is_video' => $photo->isVideo(),
                    'content_type' => $photo->content_type,
                ];

                if ($photo->isVideo()) {
                    $payload['videos'][] = $entry;
                } else {
                    $payload['photos'][] = $entry;
                }
            }
        }

        $payload['has_video'] = ($payload['videos'] ?? []) !== [];

        return $payload;
    }
}
