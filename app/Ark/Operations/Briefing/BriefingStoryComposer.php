<?php

namespace App\Ark\Operations\Briefing;

use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Models\User;

/**
 * Narrative layer for the operations briefing — greeting and yesterday summary only.
 */
final class BriefingStoryComposer
{
    public function greeting(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
        $firstName = $parts[0] !== '' ? $parts[0] : 'there';
        $hour = (int) OperationalReportDateScope::shopNow()->format('G');

        $salutation = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        return $salutation.', '.$firstName.'.';
    }

    public function narrativeIntro(BriefingContext $context): string
    {
        return 'Yesterday · '.OperationalReportDateScope::shopRangeLabel(
            $context->yesterdayFrom,
            $context->yesterdayTo,
        );
    }

    /**
     * @return list<array{label: string, value: string, hint: string|null}>
     */
    public function yesterdaySummary(BriefingContext $context): array
    {
        $eod = EndOfDayReportProjection::resolve($context->yesterdayFrom, $context->yesterdayTo);
        $effectiveness = collect($eod->salesEffectiveness);

        $summary = [];

        $sales = collect($eod->roSummary)->firstWhere('label', 'Posted invoice sales');
        if (is_array($sales)) {
            $summary[] = [
                'label' => 'Posted invoice sales',
                'value' => (string) $sales['value'],
                'hint' => 'Frozen invoice, before tax. Write-offs are separate.',
            ];
        }

        $postedRos = $effectiveness->firstWhere('label', 'Car count');
        if (is_array($postedRos)) {
            $summary[] = [
                'label' => 'Car count',
                'value' => (string) $postedRos['value'],
                'hint' => $postedRos['hint'] ?? 'Posted repair orders',
            ];
        }

        return $summary;
    }

    public function emptyAttentionMessage(): string
    {
        return 'Everything requiring attention is currently handled. Yesterday\'s operations completed successfully.';
    }
}
