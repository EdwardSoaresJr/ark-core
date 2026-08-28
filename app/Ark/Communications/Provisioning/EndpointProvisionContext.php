<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;

/**
 * Authority inputs packaged for endpoint serialization — provisioning reads only.
 */
final readonly class EndpointProvisionContext
{
    public function __construct(
        public CommunicationDevice $device,
        public TelephonyExtension $extension,
        public ?CommunicationDeviceModel $deviceModel,
    ) {}
}
