<?php

namespace App\Ark\Operations\Today\Surface;

use Illuminate\Support\Carbon;

final readonly class TodayProjection
{
    /**
     * @param  list<TodaySection>  $sections
     * @param  list<string>  $whyTodayLines
     */
    public function __construct(
        public string $greeting,
        public int $attentionCount,
        public string $attentionIntro,
        public ?string $caughtUpDetail,
        public string $caughtUpFocus,
        public array $sections,
        public array $whyTodayLines,
        public TodayLens $lens,
        public Carbon $generatedAt,
        public ?ShopDashboardProjection $shopDashboard = null,
    ) {}

    /**
     * @return list<TodaySection>
     */
    public function nonEmptySections(): array
    {
        return array_values(array_filter(
            $this->sections,
            static fn (TodaySection $section): bool => ! $section->isEmpty(),
        ));
    }

    public function showsShopDashboard(): bool
    {
        return $this->shopDashboard !== null;
    }
}
