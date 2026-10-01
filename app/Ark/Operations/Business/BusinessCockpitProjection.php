<?php

namespace App\Ark\Operations\Business;

use App\Ark\Operations\Today\Surface\TodaySection;

final readonly class BusinessCockpitProjection
{
    /**
     * @param  list<TodaySection>  $sections
     * @param  list<array{label: string, value: string, hint: string|null}>  $yesterdaySummary
     * @param  list<array{label: string, url: string}>  $links
     */
    public function __construct(
        public string $greeting,
        public array $sections,
        public array $yesterdaySummary,
        public array $links,
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
}
