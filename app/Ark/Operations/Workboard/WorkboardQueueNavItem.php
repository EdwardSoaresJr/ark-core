<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardQueueNavItem
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public string $url,
        public bool $isActive,
        public string $countSeverity = 'neutral',
        public bool $isSelectable = true,
    ) {}
}
