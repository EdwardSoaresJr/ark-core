<?php

namespace App\Ark\Operations\Payments;

final class CustomerPayTokenResult
{
    public function __construct(
        public readonly CustomerDocumentAccessToken $token,
        public readonly string $plainToken,
    ) {}
}
