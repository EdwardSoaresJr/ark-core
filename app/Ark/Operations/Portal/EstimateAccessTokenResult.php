<?php

namespace App\Ark\Operations\Portal;

final class EstimateAccessTokenResult
{
    public function __construct(
        public readonly EstimateAccessToken $token,
        public readonly string $plainToken,
        public readonly bool $reused,
    ) {}
}
