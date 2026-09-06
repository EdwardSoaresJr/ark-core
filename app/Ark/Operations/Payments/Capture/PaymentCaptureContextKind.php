<?php

namespace App\Ark\Operations\Payments\Capture;

enum PaymentCaptureContextKind: string
{
    case Payment = 'payment';
    case Deposit = 'deposit';
}
