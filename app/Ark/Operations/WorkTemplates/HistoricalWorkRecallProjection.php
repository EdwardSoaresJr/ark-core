<?php

namespace App\Ark\Operations\WorkTemplates;

/**
 * Disposable projection: historical labor confidence for a Saved Work selection.
 * Owns nothing. Zero persistence. Never OEM/factory/book-time language.
 *
 * @phpstan-type HistoricalSample array{
 *     hours: float,
 *     repair_order_id: int,
 *     work_group_id: int,
 *     vehicle_summary: string,
 *     posted_at: string|null
 * }
 */
final class HistoricalWorkRecallProjection
{
    /**
     * @param  list<HistoricalSample>  $samples
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly HistoricalMatchTier $tier,
        public readonly int $sampleCount,
        public readonly ?float $medianHours,
        public readonly ?float $minHours,
        public readonly ?float $maxHours,
        public readonly ?string $mostRecentAt,
        public readonly ?string $comparableVehicleSummary,
        public readonly array $reasons,
        public readonly array $samples,
        public readonly ?float $templateDefaultHours,
        public readonly bool $preparesLabor,
    ) {}

    public static function none(?float $templateDefaultHours): self
    {
        return new self(
            tier: HistoricalMatchTier::None,
            sampleCount: 0,
            medianHours: null,
            minHours: null,
            maxHours: null,
            mostRecentAt: null,
            comparableVehicleSummary: null,
            reasons: ['No comparable shop history found.'],
            samples: [],
            templateDefaultHours: $templateDefaultHours,
            preparesLabor: false,
        );
    }

    /**
     * Labor hours the Saved Work preview should show as the editable default.
     * Possible/None keep template default (or null).
     */
    public function previewLaborHours(): ?float
    {
        if ($this->preparesLabor && $this->medianHours !== null) {
            return $this->medianHours;
        }

        return $this->templateDefaultHours;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier->value,
            'tier_label' => $this->tier->label(),
            'sample_count' => $this->sampleCount,
            'sample_label' => $this->sampleCount === 1
                ? '1 prior comparable repair'
                : ($this->sampleCount > 0 ? $this->sampleCount.' comparable completed repairs' : null),
            'median_hours' => $this->medianHours,
            'min_hours' => $this->minHours,
            'max_hours' => $this->maxHours,
            'most_recent_at' => $this->mostRecentAt,
            'comparable_vehicle_summary' => $this->comparableVehicleSummary,
            'reasons' => $this->reasons,
            'prepares_labor' => $this->preparesLabor,
            'requires_review' => $this->tier->requiresReviewBeforeApply(),
            'preview_labor_hours' => $this->previewLaborHours(),
            'template_default_hours' => $this->templateDefaultHours,
            'source_label' => $this->sourceLabel(),
            'samples' => $this->samples,
        ];
    }

    private function sourceLabel(): string
    {
        return match ($this->tier) {
            HistoricalMatchTier::Exact => 'Based on your shop history',
            HistoricalMatchTier::Likely => 'Suggested from shop history — review before adding',
            HistoricalMatchTier::Possible => 'Historical reference only — labor not automatically applied',
            HistoricalMatchTier::None => 'Saved Work default',
        };
    }
}
