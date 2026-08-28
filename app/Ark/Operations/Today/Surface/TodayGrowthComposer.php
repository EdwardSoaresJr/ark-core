<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityEffort;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class TodayGrowthComposer
{
    public function __construct(
        private readonly OpportunityQueueRepository $opportunities,
        private readonly TodayOwnerResolver $owners,
    ) {}

    public function section(User $user): ?TodaySection
    {
        if (! $user->can(ArkCapability::GrowthAccess->value)) {
            return null;
        }

        $this->opportunities->syncDiscovered();

        $actions = [];

        $actionable = GrowthOpportunity::query()
            ->whereIn('status', [
                GrowthOpportunityStatus::Discovered,
                GrowthOpportunityStatus::Accepted,
                GrowthOpportunityStatus::Building,
            ])
            ->orderByDesc('priority_score')
            ->limit(3)
            ->get();

        foreach ($actionable as $opportunity) {
            $actions[] = $this->publishAction($opportunity);
        }

        $inFlight = GrowthOpportunity::query()
            ->whereIn('status', [GrowthOpportunityStatus::Measuring, GrowthOpportunityStatus::Published])
            ->orderByDesc('updated_at')
            ->limit(2)
            ->get();

        foreach ($inFlight as $opportunity) {
            $actions[] = $this->measuringAction($opportunity);
        }

        $improve = GrowthOpportunity::query()
            ->where('action_type', GrowthOpportunityAction::Improve)
            ->whereIn('status', [GrowthOpportunityStatus::Discovered, GrowthOpportunityStatus::Accepted])
            ->orderByDesc('priority_score')
            ->limit(1)
            ->get();

        foreach ($improve as $opportunity) {
            $actions[] = $this->improveAction($opportunity);
        }

        if ($actions === []) {
            return null;
        }

        return new TodaySection(
            key: 'this_week',
            title: 'This week',
            actions: array_slice($actions, 0, 4),
        );
    }

    private function publishAction(GrowthOpportunity $opportunity): TodayAction
    {
        $title = $this->pageTitle($opportunity);

        return new TodayAction(
            key: 'growth_'.$opportunity->id,
            title: 'Publish '.$title,
            ownerLabel: $this->owners->growthOwnerLabel(),
            url: route('growth.opportunities.build', $opportunity),
            whyYouLabel: 'You own Growth.',
            expectedOutcome: 'More qualified organic traffic.',
            effortLabel: $this->effortHours($opportunity->effort),
            reason: $this->createReason($opportunity),
        );
    }

    private function measuringAction(GrowthOpportunity $opportunity): TodayAction
    {
        return new TodayAction(
            key: 'growth_measure_'.$opportunity->id,
            title: $this->pageTitle($opportunity).' moved to measuring',
            ownerLabel: $this->owners->growthOwnerLabel(),
            url: route('growth.opportunities.build', $opportunity),
            whyYouLabel: 'You own Growth.',
            expectedOutcome: 'Confirm whether the page is earning traffic.',
            reason: 'Leave alone — measurement window is running',
        );
    }

    private function improveAction(GrowthOpportunity $opportunity): TodayAction
    {
        $title = $this->pageTitle($opportunity);

        return new TodayAction(
            key: 'growth_improve_'.$opportunity->id,
            title: 'Improve '.$title,
            ownerLabel: $this->owners->growthOwnerLabel(),
            url: route('growth.opportunities.build', $opportunity),
            whyYouLabel: 'You own Growth.',
            expectedOutcome: 'Stronger click-through from existing traffic.',
            effortLabel: $this->effortHours($opportunity->effort),
            reason: $this->improveReason($opportunity),
        );
    }

    private function pageTitle(GrowthOpportunity $opportunity): string
    {
        $title = $opportunity->title;

        if (str_contains(strtolower($title), 'create')) {
            $title = (string) ($opportunity->search_query ?? $title);
        }

        $title = preg_replace('/^(create|improve)\s+page\s+(for\s+)?/i', '', $title) ?? $title;

        return ucwords(trim($title));
    }

    private function effortHours(GrowthOpportunityEffort $effort): string
    {
        return match ($effort) {
            GrowthOpportunityEffort::Small => '2 hours',
            GrowthOpportunityEffort::Medium => '4 hours',
            GrowthOpportunityEffort::Large => '8 hours',
        };
    }

    private function createReason(GrowthOpportunity $opportunity): string
    {
        $facts = collect($opportunity->evidence['facts'] ?? []);
        $impressions = $facts->firstWhere('label', 'Impressions (28d)')['value'] ?? null;

        $parts = ['High demand'];

        if ($impressions !== null) {
            $parts[0] = number_format((int) str_replace(',', '', (string) $impressions)).' monthly searches';
        }

        $parts[] = 'No page exists';

        return implode(' · ', $parts);
    }

    private function improveReason(GrowthOpportunity $opportunity): string
    {
        $facts = collect($opportunity->evidence['facts'] ?? []);
        $ctr = $facts->firstWhere('label', 'CTR (28d)')['value'] ?? null;

        if ($ctr !== null) {
            return 'Traffic exists · weak click-through ('.$ctr.')';
        }

        return 'Page underperforming — room to improve';
    }
}
