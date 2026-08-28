<?php

namespace App\Ark\Operations\Learn;

final readonly class LearnTrainingShellProjection
{
    /**
     * @param  array{active: bool, snoozed_at: string, snoozed_until: string, snoozed_until_label: string, hours: int}|null  $snoozeState
     */
    public function __construct(
        public bool $isCurrent,
        public ?array $snoozeState,
        public bool $canSnoozeTraining,
    ) {}

    public function isGateOpen(): bool
    {
        return $this->isCurrent || $this->snoozeState !== null;
    }
}
