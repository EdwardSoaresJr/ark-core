<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class CreateRecommendationFromInspectionItemAction
{
    public function __construct(
        private readonly CreateRecommendationAction $create,
    ) {}

    /**
     * @param  array{
     *     title?: ?string,
     *     customer_description?: ?string,
     *     advisor_note?: ?string,
     *     safety_related?: ?bool,
     *     due_kind?: RecommendationDueKind|string|null,
     *     urgency?: RecommendationUrgency|string|null,
     * }  $overrides
     */
    public function handle(InspectionItem $item, RepairOrder $repairOrder, array $overrides = []): Recommendation
    {
        $item->loadMissing(['inspection.repairOrder.customer', 'inspection.repairOrder.vehicle', 'concern']);

        $existing = Recommendation::query()
            ->where('originating_inspection_item_id', $item->id)
            ->where('lifecycle', RecommendationLifecycle::Open)
            ->first();

        if ($existing instanceof Recommendation) {
            return $existing;
        }

        $repairOrder->loadMissing(['customer', 'vehicle']);
        $intent = InspectionFindingIntent::tryFromNotes($item->notes);
        $failed = $item->observed_state === InspectionObservedState::Fail
            || $item->observed_state === InspectionObservedState::NeedsAttention;
        $safety = (bool) ($overrides['safety_related'] ?? ($intent === InspectionFindingIntent::Safety || $item->observed_state === InspectionObservedState::Fail));
        $dueKind = $overrides['due_kind']
            ?? ($failed || $safety ? RecommendationDueKind::Now : RecommendationDueKind::None);
        $urgency = $overrides['urgency']
            ?? ($safety || $item->observed_state === InspectionObservedState::Fail
                ? RecommendationUrgency::Now
                : RecommendationUrgency::Soon);

        $title = trim((string) ($overrides['title'] ?? ''));
        if ($title === '') {
            $title = $this->defaultTitle($item);
        }

        return $this->create->handle([
            'customer' => $repairOrder->customer,
            'vehicle' => $repairOrder->vehicle,
            'title' => $title,
            'customer_description' => $overrides['customer_description'] ?? InspectionFindingIntent::stripNotesPrefix($item->notes),
            'advisor_note' => $overrides['advisor_note'] ?? null,
            'originating_repair_order' => $repairOrder,
            'originating_inspection' => $item->inspection,
            'originating_inspection_item' => $item,
            'originating_concern' => $item->concern,
            'discovered_mileage' => $repairOrder->resolvedMileageIn(),
            'urgency' => $urgency,
            'safety_related' => $safety,
            'due_kind' => $dueKind,
            'source_kind' => RecommendationSourceKind::Inspection,
        ]);
    }

    private function defaultTitle(InspectionItem $item): string
    {
        $label = trim((string) $item->label);

        if ($label === '') {
            return 'Inspection recommendation';
        }

        if (str_starts_with(strtolower($label), 'replace') || str_starts_with(strtolower($label), 'repair')) {
            return $label;
        }

        return match ($item->observed_state) {
            InspectionObservedState::Fail, InspectionObservedState::NeedsAttention => 'Replace '.$label,
            InspectionObservedState::Monitor => 'Monitor '.$label,
            default => $label,
        };
    }
}
