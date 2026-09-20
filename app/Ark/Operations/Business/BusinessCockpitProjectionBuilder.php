<?php

namespace App\Ark\Operations\Business;

use App\Ark\Operations\Briefing\BriefingStoryComposer;
use App\Ark\Operations\Briefing\OperationsBriefingProjection;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class BusinessCockpitProjectionBuilder
{
    public function __construct(
        private readonly OperationsBriefingProjection $briefingProjection,
        private readonly BriefingStoryComposer $story,
    ) {}

    public function forUser(User $user): BusinessCockpitProjection
    {
        abort_unless(BusinessWorkspaceAccess::allows($user), 403);

        $context = $this->briefingProjection->contextFor($user, null);

        return new BusinessCockpitProjection(
            greeting: $this->story->greeting($user),
            sections: [],
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
                'label' => 'Day Review',
                'url' => route('operations.owner.day-review'),
            ];
        }

        if ($user->can(ArkCapability::FinancialView->value)) {
            $links[] = [
                'label' => 'Reports',
                'url' => route('operations.reports.index'),
            ];
        }

        return $links;
    }
}
