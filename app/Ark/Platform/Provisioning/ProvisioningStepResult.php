<?php

namespace App\Ark\Platform\Provisioning;

final readonly class ProvisioningStepResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $failureReason = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $reason): self
    {
        return new self(false, $reason);
    }
}
