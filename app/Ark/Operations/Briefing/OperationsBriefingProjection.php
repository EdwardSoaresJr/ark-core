<?php

namespace App\Ark\Operations\Briefing;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Disposable morning briefing — composes existing projections and explainable rules.
 */
final class OperationsBriefingProjection
{
    public function __construct(
        private readonly BriefingRepository $repository,
        private readonly BriefingStoryComposer $composer,
        private readonly BriefingEvidenceResolver $evidenceResolver,
    ) {}

    public function forUser(User $user, ?string $briefingDate = null): OperationsBriefing
    {
        $context = $this->contextFor($user, $briefingDate);
        $items = $this->repository->attentionItems($context);

        $sections = $items === []
            ? []
            : [
                new BriefingSection(
                    key: 'attention',
                    title: 'Attention items',
                    items: $items,
                    intro: 'What deserves your attention today.',
                ),
            ];

        return new OperationsBriefing(
            greeting: $this->composer->greeting($user),
            narrativeIntro: $this->composer->narrativeIntro($context),
            yesterdaySummary: $this->composer->yesterdaySummary($context),
            sections: $sections,
            hasAttentionItems: $items !== [],
            emptyAttentionMessage: $this->composer->emptyAttentionMessage(),
            generatedAt: now(),
            briefingDateLabel: OperationalReportDateScope::shopRangeLabel(
                $context->briefingDate->copy()->startOfDay(),
                $context->briefingDate->copy()->endOfDay(),
            ),
        );
    }

    /**
     * @param  list<array{type: string, id?: int|null, summary?: string, detail?: string, occurred_at?: string}>  $references
     * @return list<BriefingEvidenceItem>
     */
    public function expandEvidence(array $references): array
    {
        return $this->evidenceResolver->resolve($references);
    }

    public function contextFor(User $user, ?string $briefingDate = null): BriefingContext
    {
        return $this->buildContext($user, $briefingDate);
    }

    private function buildContext(User $user, ?string $briefingDate): BriefingContext
    {
        $shopTz = OperationalReportDateScope::displayTimezone();

        $briefingInstant = filled($briefingDate)
            ? Carbon::parse($briefingDate, $shopTz)->startOfDay()
            : OperationalReportDateScope::shopNow()->copy()->startOfDay();

        $yesterdayShop = $briefingInstant->copy()->subDay();
        $yesterdayLabel = OperationalReportDateScope::shopDateString($yesterdayShop);

        [$yesterdayFrom, $yesterdayTo] = OperationalReportDateScope::resolveRange(
            $yesterdayLabel,
            $yesterdayLabel,
        );

        $priorWeekEndShop = $yesterdayShop->copy()->subDay();
        $priorWeekStartShop = $priorWeekEndShop->copy()->subDays(6);
        $priorWeekStartLabel = OperationalReportDateScope::shopDateString($priorWeekStartShop);
        $priorWeekEndLabel = OperationalReportDateScope::shopDateString($priorWeekEndShop);

        [$priorWeekFrom, $priorWeekTo] = OperationalReportDateScope::resolveRange(
            $priorWeekStartLabel,
            $priorWeekEndLabel,
        );

        return new BriefingContext(
            user: $user,
            briefingDate: $briefingInstant,
            yesterdayFrom: $yesterdayFrom,
            yesterdayTo: $yesterdayTo,
            priorWeekFrom: $priorWeekFrom,
            priorWeekTo: $priorWeekTo,
        );
    }
}
