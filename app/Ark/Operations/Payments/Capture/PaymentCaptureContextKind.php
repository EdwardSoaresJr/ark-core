<?php

namespace App\Ark\Operations\Payments\Capture;

enum PaymentCaptureContextKind: string
{
    case Payment = 'payment';
    case Deposit = 'deposit';
    /** One ARK Pay charge allocated across one or more customer invoices. */
    case AccountsReceivable = 'accounts_receivable';
}
