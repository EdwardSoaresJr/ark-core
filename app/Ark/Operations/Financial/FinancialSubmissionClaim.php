<?php

namespace App\Ark\Operations\Financial;

final class FinancialSubmissionClaim
{
    private function __construct(
        public bool $replayed,
        public ?FinancialSubmissionIntent $intent,
        public ?string $intentKey,
        public ?string $fingerprint,
    ) {}

    public static function replay(FinancialSubmissionIntent $intent): self
    {
        return new self(true, $intent, $intent->intent_key, $intent->payload_fingerprint);
    }

    public static function pending(string $intentKey, string $fingerprint): self
    {
        return new self(false, null, $intentKey, $fingerprint);
    }

    public static function proceed(FinancialSubmissionIntent $intent): self
    {
        return new self(false, $intent, $intent->intent_key, $intent->payload_fingerprint);
    }

    public static function unguarded(): self
    {
        return new self(false, null, null, null);
    }
}
