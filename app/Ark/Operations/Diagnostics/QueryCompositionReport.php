<?php

namespace App\Ark\Operations\Diagnostics;

final class QueryCompositionReport
{
    /**
     * @param  array<string, int>  $counts
     * @param  array<string, list<string>>  $samples
     * @param  array<string, array<string, int>>  $subcounts
     * @param  list<array{operation: string, table: string, subsystem: string, sql: string}>  $getMutations
     */
    public function __construct(
        public readonly int $totalQueries,
        public readonly array $counts,
        public readonly array $samples,
        public readonly int $updateQueries,
        public readonly array $subcounts = [],
        public readonly array $getMutations = [],
    ) {}

    /**
     * @return list<string>
     */
    public static function categoryOrder(): array
    {
        return [
            'ControllerEagerLoad',
            'FinancialPresenter',
            'Posture',
            'LifecycleProjection',
            'LifecycleControls',
            'CommunicationTimeline',
            'Documents',
            'Authorization',
            'ConcernsAndLines',
            'IdentityPresenters',
            'AdvisorWork',
            'Concurrency',
            'ShopSettings',
            'Staff',
            'Portal',
            'PartsCatalog',
            'AuthorizationChecks',
            'ViewComposers',
            'BladeMisc',
            'FrameworkMisc',
            'Miscellaneous',
        ];
    }

    /**
     * @return list<array{category: string, queries: int, share: string}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach (self::categoryOrder() as $category) {
            $count = $this->counts[$category] ?? 0;

            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'category' => $category,
                'queries' => $count,
                'share' => $this->totalQueries > 0
                    ? number_format(($count / $this->totalQueries) * 100, 1).'%'
                    : '0%',
            ];
        }

        $known = array_sum($this->counts);
        $remainder = max(0, $this->totalQueries - $known);

        if ($remainder > 0) {
            $rows[] = [
                'category' => 'Uncategorized',
                'queries' => $remainder,
                'share' => number_format(($remainder / $this->totalQueries) * 100, 1).'%',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{category: string, queries: int, share: string}>
     */
    public function subcategoryRows(string $parentCategory): array
    {
        $subcounts = $this->subcounts[$parentCategory] ?? [];
        $parentTotal = $this->counts[$parentCategory] ?? 0;

        if ($subcounts === [] || $parentTotal === 0) {
            return [];
        }

        $rows = [];

        foreach (self::subcategoryOrder($parentCategory) as $subcategory) {
            $count = $subcounts[$subcategory] ?? 0;

            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'category' => $subcategory,
                'queries' => $count,
                'share' => number_format(($count / $parentTotal) * 100, 1).'% of '.$parentCategory,
            ];
        }

        foreach ($subcounts as $subcategory => $count) {
            if ($count === 0 || in_array($subcategory, self::subcategoryOrder($parentCategory), true)) {
                continue;
            }

            $rows[] = [
                'category' => $subcategory,
                'queries' => $count,
                'share' => number_format(($count / $parentTotal) * 100, 1).'% of '.$parentCategory,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function subcategoryOrder(string $parentCategory): array
    {
        return match ($parentCategory) {
            'FrameworkMisc' => [
                'Permissions',
                'PolicyChecks',
                'LearnTraining',
                'Telephony',
                'Presence',
                'Auth',
                'RouteBinding',
                'StatusCatalog',
                'StaffLookup',
                'BladeIncludes',
                'Cache',
                'LaravelFramework',
                'Misc',
            ],
            'ViewComposers' => [
                'WorkspaceTabRepairOrder',
                'WorkspaceTabCustomer',
                'WorkspaceTabVehicle',
                'WorkspaceTabIntake',
                'WorkspaceTabSignals',
                'LearnTrainingShell',
                'LayoutShell',
                'Misc',
            ],
            'FinancialPresenter' => [
                'BalanceDue',
                'Ledger',
                'Deposits',
                'Totals',
                'Closeout',
                'Posting',
                'PresenterCore',
            ],
            default => [],
        };
    }

    /**
     * @return list<array{subsystem: string, mutations: int, share: string}>
     */
    public function getMutationRows(): array
    {
        if ($this->getMutations === []) {
            return [];
        }

        $counts = [];

        foreach ($this->getMutations as $mutation) {
            $subsystem = (string) $mutation['subsystem'];
            $counts[$subsystem] = ($counts[$subsystem] ?? 0) + 1;
        }

        $total = count($this->getMutations);
        $rows = [];

        foreach ($counts as $subsystem => $count) {
            $rows[] = [
                'subsystem' => $subsystem,
                'mutations' => $count,
                'share' => number_format(($count / $total) * 100, 1).'% of GET writes',
            ];
        }

        usort($rows, fn (array $left, array $right): int => $right['mutations'] <=> $left['mutations']);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function breakdownCategories(): array
    {
        return ['FrameworkMisc', 'ViewComposers', 'FinancialPresenter'];
    }

    private function appendBreakdownSections(array &$lines): void
    {
        foreach (self::breakdownCategories() as $category) {
            if (($this->counts[$category] ?? 0) === 0) {
                continue;
            }

            $lines[] = '';
            $lines[] = "## {$category} breakdown";
            $lines[] = '';
            $lines[] = '| Subcategory | Queries | Share |';
            $lines[] = '| --- | ---: | ---: |';

            foreach ($this->subcategoryRows($category) as $row) {
                $lines[] = sprintf('| %s | %d | %s |', $row['category'], $row['queries'], $row['share']);
            }
        }

        if ($this->getMutations !== []) {
            $lines[] = '';
            $lines[] = '## GET mutations (Read/Write Audit)';
            $lines[] = '';
            $lines[] = '| Subsystem | Mutations | Share |';
            $lines[] = '| --- | ---: | ---: |';

            foreach ($this->getMutationRows() as $row) {
                $lines[] = sprintf('| %s | %d | %s |', $row['subsystem'], $row['mutations'], $row['share']);
            }
        }
    }

    public function toMarkdown(string $surface, string $route): string
    {
        $lines = [
            "# Query composition: {$surface}",
            '',
            "- Route: `{$route}`",
            "- Total queries: **{$this->totalQueries}**",
            "- `repair_order_lines` UPDATEs: **{$this->updateQueries}**",
            '- GET write mutations: **'.count($this->getMutations).'**',
            '',
            '| Source | Queries | Share |',
            '| --- | ---: | ---: |',
        ];

        foreach ($this->rows() as $row) {
            $lines[] = sprintf('| %s | %d | %s |', $row['category'], $row['queries'], $row['share']);
        }

        $this->appendBreakdownSections($lines);

        $lines[] = '';
        $lines[] = '## Sample queries';

        foreach ($this->samples as $category => $queries) {
            if ($queries === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = "### {$category}";
            $lines[] = '';

            foreach ($queries as $query) {
                $lines[] = '- `'.$query.'`';
            }
        }

        return implode("\n", $lines);
    }
}
