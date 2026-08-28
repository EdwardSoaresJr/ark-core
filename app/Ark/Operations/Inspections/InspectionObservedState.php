<?php

namespace App\Ark\Operations\Inspections;

enum InspectionObservedState: string
{
    case NotChecked = 'not_checked';
    case Pass = 'pass';
    case Monitor = 'monitor';
    case NeedsAttention = 'needs_attention';
    case Fail = 'fail';
    case Measure = 'measure';
    case Na = 'na';

    public function label(): string
    {
        return match ($this) {
            self::NotChecked => 'Not checked',
            self::Pass => 'Pass',
            self::Monitor => 'Monitor',
            self::NeedsAttention => 'Needs Attention',
            self::Fail => 'Fail',
            self::Measure => 'Measure',
            self::Na => 'N/A',
        };
    }

    /** Technician checklist vocabulary — no authority jargon on mobile. */
    public function checklistLabel(): string
    {
        return match ($this) {
            self::NotChecked => 'Unchecked',
            self::Pass => 'Good',
            self::Monitor => 'Monitor',
            self::NeedsAttention => 'Needs Attention',
            self::Fail => 'Failed',
            self::Measure => 'Measure',
            self::Na => 'N/A',
        };
    }

    public function requiresEvidencePrompt(): bool
    {
        return in_array($this, [self::NeedsAttention, self::Fail], true);
    }

    /**
     * @return list<InspectionObservedState>
     */
    public static function checklistOptions(): array
    {
        return [
            self::Pass,
            self::Monitor,
            self::NeedsAttention,
            self::Fail,
            self::Na,
        ];
    }
}
