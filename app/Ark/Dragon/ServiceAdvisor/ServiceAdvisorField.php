<?php

namespace App\Ark\Dragon\ServiceAdvisor;

enum ServiceAdvisorField: string
{
    case Summary = 'summary';
    case CustomerStates = 'customer_states';
    case VerifiedFindings = 'verified_findings';
    case DtcsSummary = 'dtcs_summary';
    case Recommendation = 'recommendation';
    case VisitReason = 'visit_reason';
    case LineNote = 'line_note';

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Concern summary',
            self::CustomerStates => 'Customer states',
            self::VerifiedFindings => 'Verified findings',
            self::DtcsSummary => 'DTCs',
            self::Recommendation => 'Recommendation',
            self::VisitReason => 'Reason for visit',
            self::LineNote => 'Line note',
        };
    }

    public function isConcernNarrative(): bool
    {
        return match ($this) {
            self::Summary,
            self::CustomerStates,
            self::VerifiedFindings,
            self::DtcsSummary,
            self::Recommendation => true,
            self::VisitReason, self::LineNote => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $f): string => $f->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function concernNarrativeValues(): array
    {
        return array_values(array_map(
            static fn (self $f): string => $f->value,
            array_filter(self::cases(), static fn (self $f): bool => $f->isConcernNarrative()),
        ));
    }
}
