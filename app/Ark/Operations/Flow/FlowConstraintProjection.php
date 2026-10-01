<?php

namespace App\Ark\Operations\Flow;

final readonly class FlowConstraintProjection
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public FlowStageKey $stageKey,
        public string $label,
        public int $pressureScore,
        public string $headline,
        public array $reasons,
    ) {}
}
