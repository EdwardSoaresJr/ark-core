<?php

namespace App\Ark\Operations\Workboard;

final readonly class WorkboardCardGlance
{
    public const ATTENTION_NORMAL = 'normal';

    public const ATTENTION_WATCH = 'watch';

    public const ATTENTION_ATTENTION = 'attention';

    public const ATTENTION_CRITICAL = 'critical';

    public function __construct(
        public string $whyLabel,
        public string $nextLabel,
        public ?string $moneyLabel,
        public ?string $moneyCaption,
        public string $ageLabel,
        public bool $waitingOnCustomerDecision,
        public string $operationalStatus,
        public string $clockLabel,
        public string $attention,
        public bool $statusRestatesLane = false,
        public ?string $configuredStatusColor = null,
    ) {}
}
