<?php

namespace App\Ark\Runtime;

final class DemoInstall
{
    public const REPOSITORY_URL = 'https://github.com/EdwardSoaresJr/ark-core';

    public const HOSTED_URL = 'https://github.com/EdwardSoaresJr/ark-core#hosted-ark';

    public static function isDemo(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === 'demo.arksms.com' || str_ends_with($host, '.demo-auto.test');
    }
}
