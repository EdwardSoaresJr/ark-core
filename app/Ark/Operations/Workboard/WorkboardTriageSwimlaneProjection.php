<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardTriageSwimlaneProjection
{
    /**
     * @param  list<WorkboardTriageLaneProjection>  $lanes
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $lanes,
    ) {}
}
