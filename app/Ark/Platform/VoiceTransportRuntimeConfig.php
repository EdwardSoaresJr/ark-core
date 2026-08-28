<?php

namespace App\Ark\Platform;

final class VoiceTransportRuntimeConfig
{
    public static function apply(): void
    {
        VoiceTransportConfiguration::applyRuntimeConfig();
    }
}
