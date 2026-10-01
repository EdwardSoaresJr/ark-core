<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use RuntimeException;

final class CoolifyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $operation = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct(CoolifyMessageSanitizer::sanitize($message));
    }
}
