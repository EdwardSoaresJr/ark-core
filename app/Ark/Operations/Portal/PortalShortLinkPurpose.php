<?php

namespace App\Ark\Operations\Portal;

enum PortalShortLinkPurpose: string
{
    case Estimate = 'estimate';
    case Inspection = 'inspection';
    case Payment = 'payment';
    case Deposit = 'deposit';
}
