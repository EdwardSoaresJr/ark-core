<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardQueueNavGroup
{
    /**
     * @param  list<WorkboardQueueNavItem>  $items
     */
    public function __construct(
        public ?string $label,
        public array $items,
    ) {}
}
