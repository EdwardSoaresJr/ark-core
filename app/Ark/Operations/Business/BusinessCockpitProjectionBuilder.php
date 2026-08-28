<?php

namespace App\Ark\Operations\Business;

use App\Ark\Operations\Briefing\BriefingStoryComposer;
use App\Ark\Operations\Briefing\OperationsBriefingProjection;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Operations\Today\Surface\TodayGrowthComposer;
use App\Ark\Operations\Today\Surface\TodayMarketPressureComposer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class BusinessCockpitProjectionBuilder
{
    public function __construct(
        private readonly OperationsBriefingProjection $briefingProjection,
        private readonly BriefingStoryComposer $story,
        private readonly TodayMarketPressureComposer $marketPressure,
        private readonly TodayGrowthComposer $growth,
    ) {}

    public function forUser(User $user): BusinessCockpitProjection
    {
        abort_unless(BusinessWorkspaceAccess::allows($user), 403);

        $context = $this->briefingProjection->contextFor($user, null);
        $sections = [];

        $market = $this->marketPressure->section($user);
        if ($market !== null) {
            $sections[] = $market;
        }

        $growth = $this->growth->section($user);
        if ($growth !== null) {
            $sections[] = $growth;
        }

        return new BusinessCockpitProjection(
            greeting: $this->story->greeting($user),
            sections: $sections,
            yesterdaySummary: $this->story->yesterdaySummary($context),
            links: $this->links($user),
        );
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function links(User $user): array
    {
        $links = [];

        if (OwnerWorkspaceAccess::allows($user)) {
            $links[] = [
                'label' => 'Bookend',
                'url' => route('operations.owner.bookend'),
            ];
        }

        if ($user->can(ArkCapability::FinancialView->value)) {
            $links[] = [
                'label' => 'Reports',
                'url' => route('operations.reports.index'),
            ];
        }

        if ($user->can(ArkCapability::SettingsManage->value)) {
            $links[] = [
                'label' => 'Website Performance',
                'url' => route('website.performance'),
            ];
        }

        if ($user->can(ArkCapability::GrowthAccess->value)) {
            $links[] = [
                'label' => 'Growth',
                'url' => route('growth.opportunities.index'),
            ];
        }

        return $links;
    }
}
