<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Growth\Authority\GrowthPressureProjection;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class TodayMarketPressureComposer
{
    public function __construct(
        private readonly GrowthPressureProjection $pressure,
        private readonly TodayOwnerResolver $owners,
    ) {}

    public function section(User $user): ?TodaySection
    {
        if (! $user->can(ArkCapability::GrowthAccess->value)) {
            return null;
        }

        $panel = $this->pressure->forOwnerToday();

        $actions = [];

        if ($panel['missed_count'] > 0) {
            $actions[] = new TodayAction(
                key: 'market_missed_reviews',
                title: $panel['missed_count'].' missed review opportunit'.($panel['missed_count'] === 1 ? 'y' : 'ies'),
                ownerLabel: $this->owners->growthOwnerLabel(),
                url: $panel['detail_url'],
                whyYouLabel: 'You own the shop.',
                expectedOutcome: 'Every paid close logs a review ask.',
                reason: $panel['eligible_closes'].' paid closes · '.$panel['review_requests'].' requests recorded',
            );
        }

        return new TodaySection(
            key: 'market_pressure',
            title: 'Market pressure',
            actions: $actions,
            panel: $panel,
        );
    }
}
