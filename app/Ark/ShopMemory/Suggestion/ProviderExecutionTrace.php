<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Dev/diagnostics only — never shown in advisor UI.
 */
final class ProviderExecutionTrace
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $providerName,
        public readonly float $durationMs,
        public readonly int $resultCount,
        public readonly bool $failed = false,
        public readonly ?string $error = null,
    ) {}
}
