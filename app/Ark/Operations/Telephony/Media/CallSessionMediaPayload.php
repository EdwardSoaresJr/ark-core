<?php

namespace App\Ark\Operations\Telephony\Media;

final class CallSessionMediaPayload
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $contentType,
        public readonly CallSessionMediaUri $uri,
    ) {}
}
