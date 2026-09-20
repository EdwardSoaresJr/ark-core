<?php

namespace App\Ark\Operations\Today;

final readonly class TodayCommitmentRow
{
    public function __construct(
        public int $id,
        public string $title,
        public string $dueLabel,
        public string $reason,
        public string $ownerName,
        public int $shopRepairOrderId,
        public string $repairOrderUrl,
        public bool $isOverdue,
        public bool $isDueToday,
    ) {}
}
