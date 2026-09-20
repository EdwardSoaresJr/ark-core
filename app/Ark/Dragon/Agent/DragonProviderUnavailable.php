<?php

namespace App\Ark\Dragon\Agent;

use RuntimeException;

final class DragonProviderUnavailable extends RuntimeException
{
    public function __construct(string $message = 'Dragon model provider is unavailable.')
    {
        parent::__construct($message);
    }
}
