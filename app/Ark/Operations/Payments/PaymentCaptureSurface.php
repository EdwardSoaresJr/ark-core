<?php

namespace App\Ark\Operations\Payments;

enum PaymentCaptureSurface: string
{
    case Terminal = 'terminal';
    case Keyed = 'keyed';
    case Portal = 'portal';
    case PortalEstimateDeposit = 'portal_estimate_deposit';
    case PortalDepositRequest = 'portal_deposit_request';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Terminal => 'Card terminal',
            self::Keyed => 'Keyed card',
            self::Portal => 'Customer portal',
            self::PortalEstimateDeposit => 'Estimate deposit',
            self::PortalDepositRequest => 'Deposit request',
            self::Email => 'Emailed invoice',
        };
    }
}
