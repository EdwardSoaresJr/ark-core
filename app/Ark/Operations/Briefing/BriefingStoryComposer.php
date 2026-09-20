<?php

namespace App\Ark\Operations\Briefing;

use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
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
        $metrics = new OperationalReportRangeMetrics($context->yesterdayFrom, $context->yesterdayTo);
        $kpis = collect($metrics->kpis());
        $eod = EndOfDayReportProjection::resolve($context->yesterdayFrom, $context->yesterdayTo);
        $effectiveness = collect($eod->salesEffectiveness);

        $summary = [];

        $revenue = $kpis->firstWhere('label', 'Sales Posted');
        if (is_array($revenue)) {
            $summary[] = [
                'label' => 'Revenue',
                'value' => (string) $revenue['value'],
                'hint' => $revenue['hint'] ?? null,
            ];
        }

        $postedRos = $effectiveness->firstWhere('label', 'Total ROs');
        if (is_array($postedRos)) {
            $summary[] = [
                'label' => 'Completed repair orders',
                'value' => (string) $postedRos['value'],
                'hint' => $postedRos['hint'] ?? 'Posted in range',
            ];
        }

        $approvalRate = $kpis->firstWhere('label', 'Approval Rate');
        if (is_array($approvalRate)) {
            $summary[] = [
                'label' => 'Approval rate',
                'value' => (string) $approvalRate['value'],
                'hint' => $approvalRate['hint'] ?? null,
            ];
        }

        return $summary;
    }

    public function emptyAttentionMessage(): string
    {
        return 'Everything requiring attention is currently handled. Yesterday\'s operations completed successfully.';
    }
}
