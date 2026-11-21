<?php

namespace App\Ark\Operations\Messaging;

enum SmsDeliveryFailureClass: string
{
    case OptOut = 'opt_out';
    case UnsupportedNumber = 'unsupported_number';
    case InvalidDestination = 'invalid_destination';
    case TemporaryFailure = 'temporary_failure';
    case Unknown = 'unknown';
}
