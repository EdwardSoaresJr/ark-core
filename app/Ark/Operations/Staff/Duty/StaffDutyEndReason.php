<?php

namespace App\Ark\Operations\Staff\Duty;

enum StaffDutyEndReason: string
{
    case SignOut = 'sign_out';
    case ShopClosed = 'shop_closed';
    case MaximumLength = 'maximum_length';
    case Scheduled = 'scheduled';
    case Manual = 'manual';
}
