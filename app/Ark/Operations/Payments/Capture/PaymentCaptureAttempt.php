<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Payments\PaymentGatewayAttempt;

/**
 * Fabric lookup alias for Core payment_gateway_attempts.
 */
class PaymentCaptureAttempt extends PaymentGatewayAttempt
{
    protected $table = 'payment_gateway_attempts';
}
