<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Disposable customer/report projection of one Inspection.
 * Simple and Detailed are view modes of the same authority — never separate stores.
 */
final class InspectionReportProjection
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_DETAILED = 'detailed';

    /**
     * @param  callable(InspectionItemPhoto): string  $photoUrlResolver
     * @return array<string, mixed>
     */
    public function for(
        RepairOrder $repairOrder,
        string $mode = self::MODE_SIMPLE,
        ?callable $photoUrlResolver = null,
        bool $embedImageDataUris = false,
        ?string $liveReportUrl = null,
    ): array {
        $mode = $mode === self::MODE_DETAILED ? self::MODE_DETAILED : self::MODE_SIMPLE;

        $repairOrder->loadMissing([
            'customer',
            'vehicle',
            'inspection.template',
            'inspection.recordedBy',
            'inspection.items.measurements',
            'inspection.items.photos',
        ]);

        $inspection = $repairOrder->inspection;
        $shopLayers = app(EstimateSnapshotBuilder::class)->presentationLayers()['shop'] ?? [];

        if (! $inspection instanceof Inspection) {
            return $this->emptyReport($repairOrder, $mode, $shopLayers, $liveReportUrl);
        }

        $ordered = InspectionWalkVisibility::visibleItems(
            $inspection,
            InspectionChecklistItems::orderedChecklistItems($inspection),
        );

        // Freeform / pre-template recorded findings remain customer-visible even when
        // they are outside the template walk (historical SMS / portal compatibility).
        $orderedIds = $ordered->pluck('id')->all();
        $extras = $inspection->items
            ->filter(fn (InspectionItem $item): bool => ! $item->isSuperseded()
                && InspectionFindingCardProjection::isRecorded($item))
            ->reject(fn (InspectionItem $item): bool => in_array($item->id, $orderedIds, true))
            ->sortBy('position')
            ->values();

        // Phase 0: suppress freeform rows that collide with a customer-facing template point.
        // Orphan notes / measurements / customer evidence merge onto the authoritative point.
        $resolved = InspectionReportCollidingFindingResolver::resolve($inspection, $ordered, $extras);
        $merges = $resolved['merges'];
        $ordered = $ordered->concat($resolved['extras'])->values();

        $templateItems = InspectionTemplateItem::query()
            ->whereIn('id', $ordered->pluck('inspection_template_item_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $points = $ordered->map(function (InspectionItem $item) use (
            $templateItems,
            $inspection,
            $photoUrlResolver,
            $embedImageDataUris,
            $mode,
            $merges,
        ): array {
            $templateItem = $templateItems->get($item->inspection_template_item_id);

            $payload = $this->pointPayload(
                $item,
                $templateItem instanceof InspectionTemplateItem ? $templateItem : null,
                $inspection,
                $photoUrlResolver,
                $embedImageDataUris,
                $mode,
            );

            $freeforms = $merges[$item->id] ?? [];
            if ($freeforms !== []) {
                $payload = InspectionReportCollidingFindingResolver::mergeEvidenceIntoPayload(
                    $payload,
                    $item,
                    $freeforms,
                    $photoUrlResolver,
                    $embedImageDataUris,
                    fn (InspectionItemPhoto $photo): ?string => $this->imageDataUri($photo),
                );
                $payload['measurement_presentation'] = $this->measurementPresentation(
                    $payload['measurements'] ?? [],
                    $templateItem instanceof InspectionTemplateItem ? $templateItem : null,
                );
            }

            return $payload;
        })->values();

        $summary = $this->summary($points);
        $attention = $points->filter(fn (array $p): bool => in_array($p['condition_value'], ['needs_attention', 'failed'], true))->values();
        $monitor = $points->filter(fn (array $p): bool => $p['condition_value'] === 'monitor')->values();
        $ok = $points->filter(fn (array $p): bool => $p['condition_value'] === 'good')->values();
        $na = $points->filter(fn (array $p): bool => $p['condition_value'] === 'na')->values();

        $inspectedAt = $inspection->started_at ?? $inspection->created_at;
        $technician = $inspection->recordedBy;

        return [
            'mode' => $mode,
            'ready' => $summary['recorded_count'] > 0,
            'shop' => [
                'name' => (string) ($shopLayers['name'] ?? 'Demo Auto Repair'),
                'phone' => PhoneNumber::display($shopLayers['phone'] ?? null) ?? ($shopLayers['phone'] ?? null),
                'email' => $shopLayers['email'] ?? null,
                'website' => $shopLayers['website'] ?? null,
                'address_line_1' => $shopLayers['address_line_1'] ?? null,
                'address_line_2' => $shopLayers['address_line_2'] ?? null,
                'city' => $shopLayers['city'] ?? null,
                'state' => $shopLayers['state'] ?? null,
                'postal_code' => $shopLayers['postal_code'] ?? null,
                'logo_url' => $shopLayers['logo_url'] ?? null,
                'logo_data_uri' => $shopLayers['logo_data_uri'] ?? null,
            ],
            'vehicle' => [
                'display_name' => (string) ($repairOrder->vehicle?->display_name ?? 'Vehicle'),
                'year' => $repairOrder->vehicle?->year,
                'make' => $repairOrder->vehicle?->make,
                'model' => $repairOrder->vehicle?->model,
                'mileage_in' => $repairOrder->mileage_in,
            ],
            'identity' => [
                'repair_order_id' => (int) $repairOrder->repair_order_id,
                'title' => 'Vehicle Inspection',
                'template_name' => $inspection->template?->name ?? 'Vehicle Inspection',
                'inspected_at_label' => $inspectedAt ? ShopDisplayTimezone::formatDate($inspectedAt) : null,
                'technician_name' => $technician instanceof User ? trim((string) $technician->name) : null,
            ],
            'summary' => $summary,
            'attention_findings' => $attention->all(),
            'monitor_findings' => $monitor->all(),
            'ok_condensed' => [
                'count' => $ok->count(),
                'labels' => $ok->pluck('label')->all(),
                'by_category' => $ok->groupBy('category_name')
                    ->map(fn (Collection $group, string $category): array => [
                        'category' => $category !== '' ? $category : 'General',
                        'count' => $group->count(),
                        'labels' => $group->pluck('label')->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'na_findings' => $na->all(),
            'categories' => $points
                ->groupBy(fn (array $p): string => $p['category_name'] !== '' ? $p['category_name'] : 'General')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'points' => $group->values()->all(),
                ])
                ->values()
                ->all(),
            'points' => $points->all(),
            'live_report_url' => $liveReportUrl,
            'qr_payload' => filled($liveReportUrl) ? $liveReportUrl : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $shopLayers
     * @return array<string, mixed>
     */
    private function emptyReport(RepairOrder $repairOrder, string $mode, array $shopLayers, ?string $liveReportUrl): array
    {
        return [
            'mode' => $mode,
            'ready' => false,
            'shop' => [
                'name' => (string) ($shopLayers['name'] ?? 'Demo Auto Repair'),
                'phone' => PhoneNumber::display($shopLayers['phone'] ?? null) ?? ($shopLayers['phone'] ?? null),
                'email' => $shopLayers['email'] ?? null,
                'website' => $shopLayers['website'] ?? null,
                'address_line_1' => $shopLayers['address_line_1'] ?? null,
                'address_line_2' => $shopLayers['address_line_2'] ?? null,
                'city' => $shopLayers['city'] ?? null,
                'state' => $shopLayers['state'] ?? null,
                'postal_code' => $shopLayers['postal_code'] ?? null,
                'logo_url' => $shopLayers['logo_url'] ?? null,
                'logo_data_uri' => $shopLayers['logo_data_uri'] ?? null,
            ],
            'vehicle' => [
                'display_name' => (string) ($repairOrder->vehicle?->display_name ?? 'Vehicle'),
                'year' => $repairOrder->vehicle?->year,
                'make' => $repairOrder->vehicle?->make,
                'model' => $repairOrder->vehicle?->model,
                'mileage_in' => $repairOrder->mileage_in,
            ],
            'identity' => [
                'repair_order_id' => (int) $repairOrder->repair_order_id,
                'title' => 'Vehicle Inspection',
                'template_name' => 'Vehicle Inspection',
                'inspected_at_label' => null,
                'technician_name' => null,
            ],
            'summary' => [
                'needs_attention_count' => 0,
                'failed_count' => 0,
                'monitor_count' => 0,
                'ok_count' => 0,
                'na_count' => 0,
                'not_checked_count' => 0,
                'recorded_count' => 0,
                'headline_needs_attention' => 0,
            ],
            'attention_findings' => [],
            'monitor_findings' => [],
            'ok_condensed' => ['count' => 0, 'labels' => [], 'by_category' => []],
            'na_findings' => [],
            'categories' => [],
            'points' => [],
            'live_report_url' => $liveReportUrl,
            'qr_payload' => filled($liveReportUrl) ? $liveReportUrl : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $points
     * @return array<string, int>
     */
    private function summary(Collection $points): array
    {
        $failed = $points->where('condition_value', 'failed')->count();
        $needsAttention = $points->where('condition_value', 'needs_attention')->count();
        $monitor = $points->where('condition_value', 'monitor')->count();
        $ok = $points->where('condition_value', 'good')->count();
        $na = $points->where('condition_value', 'na')->count();
        $notChecked = $points->where('condition_value', 'not_checked')->count();
        $recorded = $points->where('recorded', true)->count();

        return [
            'needs_attention_count' => $needsAttention,
            'failed_count' => $failed,
            'monitor_count' => $monitor,
            'ok_count' => $ok,
            'na_count' => $na,
            'not_checked_count' => $notChecked,
            'recorded_count' => $recorded,
            // Top-level Simple bucket: needs_attention + fail
            'headline_needs_attention' => $needsAttention + $failed,
        ];
    }

    /**
     * @param  callable(InspectionItemPhoto): string|null  $photoUrlResolver
     * @return array<string, mixed>
     */
    private function pointPayload(
        InspectionItem $item,
        ?InspectionTemplateItem $templateItem,
        Inspection $inspection,
        ?callable $photoUrlResolver,
        bool $embedImageDataUris,
        string $mode,
    ): array {
        $state = $item->observed_state instanceof InspectionObservedState
            ? $item->observed_state
            : (InspectionObservedState::tryFrom((string) $item->observed_state) ?? InspectionObservedState::NotChecked);

        $status = InspectionChecklistStatus::fromObservedState($state);
        $conditionValue = match ($state) {
            InspectionObservedState::Pass => 'good',
            InspectionObservedState::Monitor => 'monitor',
            InspectionObservedState::NeedsAttention => 'needs_attention',
            InspectionObservedState::Fail => 'failed',
            InspectionObservedState::Na => 'na',
            InspectionObservedState::Measure => 'monitor',
            InspectionObservedState::NotChecked => 'not_checked',
        };

        $slots = InspectionMeasurementSlots::fromTemplateItem($templateItem);
        $measurements = [];
        foreach ($slots as $slot) {
            $row = $item->measurements->first(
                fn (InspectionItemMeasurement $m): bool => strcasecmp((string) $m->name, $slot['name']) === 0
                    || strcasecmp((string) $m->name, $slot['key']) === 0,
            );
            if (! $row instanceof InspectionItemMeasurement || ! filled($row->value)) {
                // Legacy single measurement without slots
                continue;
            }
            $unit = filled($row->unit) ? (string) $row->unit : ($slot['unit'] ?? null);
            $measurements[] = [
                'key' => $slot['key'],
                'name' => $slot['name'],
                'value' => (string) $row->value,
                'unit' => $unit,
                'formatted' => filled($unit)
                    ? trim((string) $row->value).' '.$unit
                    : (string) $row->value,
                'type' => $slot['type'],
            ];
        }

        if ($measurements === [] && $item->measurements->isNotEmpty()) {
            foreach ($item->measurements as $row) {
                $measurements[] = [
                    'key' => $this->legacyMeasurementKey((string) $row->name, (int) $row->id),
                    'name' => (string) $row->name,
                    'value' => (string) $row->value,
                    'unit' => $row->unit,
                    'formatted' => $row->formattedValue(),
                    'type' => 'number',
                ];
            }
        }

        $photos = [];
        $videos = [];
        foreach (InspectionCustomerEvidenceAllowlist::filterPhotos($item->photos) as $photo) {
            $url = $photoUrlResolver ? $photoUrlResolver($photo) : null;
            if ($embedImageDataUris && $photo->isImage()) {
                $url = $this->imageDataUri($photo) ?? $url;
            }

            $entry = [
                'id' => $photo->id,
                'url' => $url,
                'is_image' => $photo->isImage(),
                'is_video' => $photo->isVideo(),
                'content_type' => $photo->content_type,
            ];

            if ($photo->isVideo()) {
                $videos[] = $entry;
            } else {
                $photos[] = $entry;
            }
        }

        $comparison = $this->customerComparisonObservations($item, $inspection, $measurements);

        return [
            'id' => $item->id,
            'label' => $item->label,
            'category_name' => (string) ($item->checklist_category_name ?? $item->categoryLabel()),
            'point_key' => $templateItem?->point_key,
            'condition_value' => $conditionValue,
            'condition_label' => $status?->label() ?? 'Not checked',
            'is_failed' => $conditionValue === 'failed',
            'is_needs_attention' => $conditionValue === 'needs_attention',
            'recorded' => InspectionFindingCardProjection::isRecorded($item),
            'note' => InspectionFindingIntent::stripNotesPrefix($item->notes),
            'measurements' => $measurements,
            'measurement_presentation' => $this->measurementPresentation($measurements, $templateItem),
            'comparison_observations' => $comparison,
            'photos' => $photos,
            'videos' => $videos,
            'has_video' => $videos !== [],
            'gate_group' => $templateItem?->gate_group,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $measurements
     * @return array<string, mixed>|null
     */
    private function measurementPresentation(array $measurements, ?InspectionTemplateItem $templateItem): ?array
    {
        if ($measurements === []) {
            return null;
        }

        $byKey = collect($measurements)->keyBy('key');

        if ($byKey->has('inboard') && $byKey->has('outboard')) {
            $in = (float) $byKey['inboard']['value'];
            $out = (float) $byKey['outboard']['value'];
            $max = max($in, $out, 0.1);

            return [
                'kind' => 'brake_pads',
                'lines' => [
                    'Inboard '.$byKey['inboard']['formatted'],
                    'Outboard '.$byKey['outboard']['formatted'],
                ],
                'bars' => [
                    ['label' => 'Inboard', 'value' => $in, 'unit' => 'mm', 'pct' => round(($in / $max) * 100)],
                    ['label' => 'Outboard', 'value' => $out, 'unit' => 'mm', 'pct' => round(($out / $max) * 100)],
                ],
            ];
        }

        if ($byKey->has('outer') && $byKey->has('center') && $byKey->has('inner')) {
            $vals = [
                (float) $byKey['outer']['value'],
                (float) $byKey['center']['value'],
                (float) $byKey['inner']['value'],
            ];
            $max = max(max($vals), 0.1);

            return [
                'kind' => 'tire_tread',
                'lines' => [
                    'Outer '.$byKey['outer']['formatted'],
                    'Center '.$byKey['center']['formatted'],
                    'Inner '.$byKey['inner']['formatted'],
                ],
                'bars' => [
                    ['label' => 'Outer', 'value' => $vals[0], 'unit' => '/32"', 'pct' => round(($vals[0] / $max) * 100)],
                    ['label' => 'Center', 'value' => $vals[1], 'unit' => '/32"', 'pct' => round(($vals[1] / $max) * 100)],
                    ['label' => 'Inner', 'value' => $vals[2], 'unit' => '/32"', 'pct' => round(($vals[2] / $max) * 100)],
                ],
            ];
        }

        if ($byKey->has('lf_psi')) {
            return [
                'kind' => 'tire_psi',
                'lines' => collect(['lf_psi', 'rf_psi', 'lr_psi', 'rr_psi'])
                    ->filter(fn (string $key): bool => $byKey->has($key))
                    ->map(fn (string $key): string => $byKey[$key]['name'].' '.$byKey[$key]['formatted'])
                    ->values()
                    ->all(),
                'bars' => [],
            ];
        }

        return [
            'kind' => 'generic',
            'lines' => collect($measurements)->map(fn (array $m): string => $m['name'].': '.$m['formatted'])->all(),
            'bars' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $measurements
     * @return list<string>
     */
    private function customerComparisonObservations(
        InspectionItem $item,
        Inspection $inspection,
        array $measurements,
    ): array {
        $byKey = collect($measurements)->keyBy('key');
        $observations = [];

        if ($byKey->has('inboard') && $byKey->has('outboard')) {
            $in = (float) $byKey['inboard']['value'];
            $out = (float) $byKey['outboard']['value'];
            $delta = abs($in - $out);
            $threshold = InspectionBrakeComparison::thresholds()['same_wheel_mm'];
            if ($delta >= $threshold) {
                $observations[] = 'Uneven pad wear was observed on this wheel ('.number_format($delta, 1).' mm difference).';
            }
        }

        if ($byKey->has('outer') && $byKey->has('center') && $byKey->has('inner')) {
            $vals = [
                (float) $byKey['outer']['value'],
                (float) $byKey['center']['value'],
                (float) $byKey['inner']['value'],
            ];
            if (max($vals) - min($vals) >= 2.0) {
                $observations[] = 'Uneven tread wear was observed across this tire.';
            }
        }

        // Axle L/R pad average — observational only when comparison would fire
        $prompts = InspectionBrakeComparison::promptsForItem($item, $inspection);
        foreach ($prompts as $prompt) {
            if (($prompt['kind'] ?? '') === 'axle_lr') {
                $observations[] = 'Pad wear differs between left and right on this axle ('.number_format((float) $prompt['delta_mm'], 1).' mm).';
            }
        }

        return array_values(array_unique($observations));
    }

    private function imageDataUri(InspectionItemPhoto $photo): ?string
    {
        if (! Storage::disk('local')->exists($photo->storage_path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($photo->storage_path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $mime = $photo->content_type ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function legacyMeasurementKey(string $name, int $id): string
    {
        $normalized = strtolower(trim($name));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'inboard' => 'inboard',
            'outboard' => 'outboard',
            'outer' => 'outer',
            'center' => 'center',
            'inner' => 'inner',
            'lf_psi', 'lf', 'left_front_psi' => 'lf_psi',
            'rf_psi', 'rf', 'right_front_psi' => 'rf_psi',
            'lr_psi', 'lr', 'left_rear_psi' => 'lr_psi',
            'rr_psi', 'rr', 'right_rear_psi' => 'rr_psi',
            default => 'legacy_'.$id,
        };
    }
}
