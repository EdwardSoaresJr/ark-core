<?php

namespace App\Ark\Runtime\Exceptions;

final class ExceptionReportIdentity
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(4));
    }
}
