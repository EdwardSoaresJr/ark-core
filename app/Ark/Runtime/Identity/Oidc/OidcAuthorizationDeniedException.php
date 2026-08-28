<?php

namespace App\Ark\Runtime\Identity\Oidc;

use RuntimeException;

final class OidcAuthorizationDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
