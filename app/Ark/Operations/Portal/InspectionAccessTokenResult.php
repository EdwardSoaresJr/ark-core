<?php

namespace App\Ark\Operations\Portal;

final class InspectionAccessTokenResult
{
    public function __construct(
        public readonly InspectionAccessToken $token,
        public readonly string $plainToken,
        public readonly bool $reused,
    ) {}
}
