<?php

namespace App\Ark\Operations\Today;

final readonly class TodayCommitmentsSummary
{
    /**
     * @param  list<TodayCommitmentRow>  $rows
     */
    public function __construct(
        public int $dueTodayCount,
        public int $overdueCount,
        public array $rows,
    ) {}
}
