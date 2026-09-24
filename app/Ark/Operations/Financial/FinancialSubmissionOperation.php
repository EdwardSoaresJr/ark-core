<?php

namespace App\Ark\Operations\Financial;

enum FinancialSubmissionOperation: string
{
    case Deposit = 'deposit';
    case Payment = 'payment';
}
