<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardCardActivityMark
{
    public bool $active;

    public function __construct(
        public string $key,
        public string $shortLabel,
        public WorkboardCardActivityState $state,
        public string $tooltip,
        public ?string $badge = null,
    ) {
        $this->active = $state !== WorkboardCardActivityState::None;
    }
}
