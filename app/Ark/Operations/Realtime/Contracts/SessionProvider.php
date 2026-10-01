<?php

namespace App\Ark\Operations\Realtime\Contracts;

use App\Ark\Operations\Telephony\TelephonyProviderType;

interface SessionProvider
{
    public function providerType(): TelephonyProviderType;

    public function key(): string;
}
