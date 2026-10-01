<?php

namespace App\Ark\Operations\Payments\Capture;

enum PaymentCaptureMethod: string
{
    case Terminal = 'terminal';
    case Keyed = 'keyed';
}
