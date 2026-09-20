<?php

namespace App\Ark\Operations\WorkTemplates;

/**
 * Confidence tiers for Historical Work Recall — observational guidance only.
 * Never OEM / factory / book time.
 */
enum HistoricalMatchTier: string
{
    case Exact = 'exact';
    case Likely = 'likely';
    case Possible = 'possible';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::Likely => 'Likely',
            self::Possible => 'Possible',
            self::None => 'None',
        };
    }

    public function mayPrepareLabor(): bool
    {
        return $this === self::Exact || $this === self::Likely;
    }

    public function requiresReviewBeforeApply(): bool
    {
        return $this === self::Likely;
    }
}
