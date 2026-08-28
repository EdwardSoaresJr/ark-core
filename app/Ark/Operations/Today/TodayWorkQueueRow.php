<?php

namespace App\Ark\Operations\Today;

final readonly class TodayWorkQueueRow
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public ?string $oldestAgeLabel,
        public int $revenueTrappedCents,
        public string $revenueTrappedLabel,
        public string $workboardUrl,
    ) {}
}
