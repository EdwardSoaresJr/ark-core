<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\ShopCommunicationsSchema;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Platform\VoiceTransportConfiguration;
use RuntimeException;

final class EndpointProvisionPreflight
{
    public static function assertReady(?TelephonyExtension $extension = null): void
    {
        if (! ShopCommunicationsSchema::isReady()) {
            throw new EndpointProvisionMisconfiguredException(
                'Voice migrations pending: '.implode(', ', ShopCommunicationsSchema::missingRequirements()),
            );
        }

        try {
            VoiceTransportConfiguration::sipRegistrar();
        } catch (RuntimeException $exception) {
            throw new EndpointProvisionMisconfiguredException($exception->getMessage());
        }

        if ($extension instanceof TelephonyExtension) {
            self::assertCredential($extension);
        }
    }

    private static function assertCredential(TelephonyExtension $extension): void
    {
        if (filled($extension->secret)) {
            return;
        }

        $identity = trim((string) $extension->extension);

        if (filled(config("telephony.sip_provisioning.passwords.{$identity}"))) {
            return;
        }

        if (filled(config('telephony.sip_provisioning.default_password'))) {
            return;
        }

        throw new EndpointProvisionMisconfiguredException(
            "No SIP credential configured for extension {$identity}.",
        );
    }
}
