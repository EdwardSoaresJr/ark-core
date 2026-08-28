<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Projects shop-verified repair experience onto a common problem page.
 *
 * Authority stays in repair orders and events — this resolver will match closed
 * work to problem slugs once observation proves the linking rules.
 */
class CommonProblemShopExperienceResolver
{
    /**
     * @return array{
     *     verified_repair_count: int,
     *     most_common_fix: string|null,
     *     last_updated_label: string|null,
     *     average_diagnostic_time_label: string|null,
     *     repairs: list<array{vehicle: string, summary: string, outcome: string|null}>
     * }|null
     */
    public function forSlug(string $slug): ?array
    {
        return null;
    }
}
