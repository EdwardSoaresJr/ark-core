<?php

namespace App\Ark\Communications\Provisioning;

use Exception;

final class EndpointProvisionGateException extends Exception
{
    public function __construct(
        public readonly EndpointProvisionGateFailure $failure,
    ) {
        parent::__construct($failure->message());
    }
}
