<?php

namespace App\Ark\Operations\Flow;

use Illuminate\Support\Carbon;

final readonly class OperationalFlowProjection
{
    /**
     * @param  list<FlowStageProjection>  $stages
     */
    public function __construct(
        public array $stages,
        public ?FlowConstraintProjection $constraint,
        public Carbon $generatedAt,
    ) {}

    /**
     * Active stages for Today — constraint first, then by pressure score.
     *
     * @return list<FlowStageProjection>
     */
    public function displayStages(): array
    {
        $active = array_values(array_filter(
            $this->stages,
            fn (FlowStageProjection $stage): bool => $stage->count > 0,
        ));

        usort(
            $active,
            function (FlowStageProjection $left, FlowStageProjection $right): int {
                if ($this->constraint !== null) {
                    if ($left->stageKey === $this->constraint->stageKey) {
                        return -1;
                    }

                    if ($right->stageKey === $this->constraint->stageKey) {
                        return 1;
                    }
                }

                return $right->pressureScore <=> $left->pressureScore;
            },
        );

        return $active;
    }
}
