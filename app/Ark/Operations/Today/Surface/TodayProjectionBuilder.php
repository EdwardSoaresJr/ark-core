<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\Briefing\BriefingStoryComposer;
use App\Models\User;

final class TodayProjectionBuilder
{
    public function __construct(
        private readonly TodayLensResolver $lensResolver,
        private readonly BriefingStoryComposer $story,
        private readonly TodayOperationalComposer $operational,
        private readonly TodayExplainabilityComposer $explainability,
        private readonly ShopDashboardProjectionBuilder $shopDashboard,
    ) {}

    public function forUser(User $user): TodayProjection
    {
        $lens = $this->lensResolver->resolve($user);

        if ($lens === TodayLens::Technician) {
            $sections = $this->operational->forTechnician($user);
            $attentionCount = array_sum(array_map(
                static fn (TodaySection $section): int => $section->totalCount,
                $sections,
            ));

            return new TodayProjection(
                greeting: $this->story->greeting($user),
                attentionCount: $attentionCount,
                attentionIntro: $this->attentionIntro($attentionCount),
                caughtUpDetail: $attentionCount === 0 ? 'No critical actions require attention.' : null,
                caughtUpFocus: $this->explainability->caughtUpFocus($lens),
                sections: $sections,
                whyTodayLines: [],
                lens: $lens,
                generatedAt: now(),
                shopDashboard: null,
            );
        }

        $dashboard = $this->shopDashboard->forOpenQueue();

        return new TodayProjection(
            greeting: $this->story->greeting($user),
            attentionCount: $dashboard->carCount,
            attentionIntro: $dashboard->carCount === 0
                ? 'No open repair orders.'
                : $dashboard->carCount.' open cars on the board.',
            caughtUpDetail: null,
            caughtUpFocus: 'Keep approved work moving.',
            sections: [],
            whyTodayLines: [],
            lens: $lens,
            generatedAt: now(),
            shopDashboard: $dashboard,
        );
    }

    private function attentionIntro(int $count): string
    {
        if ($count === 0) {
            return 'You\'re caught up.';
        }

        $word = $count === 1 ? 'thing needs' : 'things need';

        return $count.' '.$word.' your attention.';
    }
}
