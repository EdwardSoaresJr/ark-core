<?php

namespace App\Ark\Platform\Website;

use RuntimeException;

final class WebsiteAuthoritySwitchException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'resolver' => 'The public site is not running the publication resolver.',
            'publication' => 'This website does not have one current publication.',
            'hash' => 'The published website does not match the live website.',
            'authority' => 'This website is not reading the live shop website.',
            default => 'This website cannot switch.',
        });
    }
}
