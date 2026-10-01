<?php

namespace App\Ark\Communications\Provisioning;

use Exception;

final class EndpointProvisionNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Endpoint device not found.');
    }
}
