<?php

namespace App\Ark\Operations\Workboard;

/**
 * One Cards-view Kanban column. Counts are filter-aware (search/tech); visible rows are capped.
 */
final readonly class JobBoardKanbanColumn
{
    /**
     * @param  list<JobBoardListRow>  $rows
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $tone,
        public string $color,
        public int $totalCount,
        public array $rows,
        public int $hiddenCount,
        public ?string $listUrl,
    ) {}
}
