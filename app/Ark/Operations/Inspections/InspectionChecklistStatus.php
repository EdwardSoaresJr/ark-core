<?php

namespace App\Ark\Operations\Inspections;

enum InspectionChecklistStatus: string
{
    case Good = 'good';
    case Monitor = 'monitor';
    case NeedsAttention = 'needs_attention';
    case Failed = 'failed';
    case Na = 'na';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Monitor => 'Monitor',
            self::NeedsAttention => 'Needs Attention',
            self::Failed => 'Failed',
            self::Na => 'N/A',
        };
    }

    public function toObservedState(): InspectionObservedState
    {
        return match ($this) {
            self::Good => InspectionObservedState::Pass,
            self::Monitor => InspectionObservedState::Monitor,
            self::NeedsAttention => InspectionObservedState::NeedsAttention,
            self::Failed => InspectionObservedState::Fail,
            self::Na => InspectionObservedState::Na,
        };
    }

    public static function fromObservedState(InspectionObservedState $state): ?self
    {
        return match ($state) {
            InspectionObservedState::Pass => self::Good,
            InspectionObservedState::Monitor => self::Monitor,
            InspectionObservedState::NeedsAttention => self::NeedsAttention,
            InspectionObservedState::Fail => self::Failed,
            InspectionObservedState::Na => self::Na,
            InspectionObservedState::Measure => self::Monitor,
            InspectionObservedState::NotChecked => null,
        };
    }

    public function requiresEvidencePrompt(): bool
    {
        return in_array($this, [self::NeedsAttention, self::Failed], true);
    }

    /**
     * @return list<InspectionChecklistStatus>
     */
    public static function ordered(): array
    {
        return [
            self::Good,
            self::Monitor,
            self::NeedsAttention,
            self::Failed,
            self::Na,
        ];
    }
}
