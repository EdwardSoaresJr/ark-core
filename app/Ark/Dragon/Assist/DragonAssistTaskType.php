<?php

namespace App\Ark\Dragon\Assist;

enum DragonAssistTaskType: string
{
    case HistoricalWorkRecallReview = 'historical_work_recall_review';
    case ServiceAdvisorRewrite = 'service_advisor_rewrite';
    case ReviewEstimateNotes = 'review_estimate_notes';

    public function label(): string
    {
        return match ($this) {
            self::HistoricalWorkRecallReview => 'Historical Work Recall review',
            self::ServiceAdvisorRewrite => 'Dragon Service Advisor rewrite',
            self::ReviewEstimateNotes => 'Review Estimate Notes',
        };
    }

    public function isSupported(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function allowlist(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
